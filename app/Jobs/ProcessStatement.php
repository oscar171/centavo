<?php

namespace App\Jobs;

use App\Ai\Agents\StatementExtractor;
use App\Enums\StatementStatus;
use App\Enums\TransactionCategory;
use App\Models\Statement;
use App\Models\Transaction;
use App\Services\AnomalyDetector;
use App\Services\StatementReconciler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Document;
use RuntimeException;
use Throwable;

class ProcessStatement implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted. No retries: an extraction
     * failure is surfaced immediately instead of tying up the worker.
     */
    public int $tries = 1;

    /**
     * The number of seconds the job can run before timing out. Kept above the
     * agent's HTTP timeout (300s) so a slow extraction fails cleanly through the
     * try/catch instead of the worker killing the whole job.
     */
    public int $timeout = 360;

    /**
     * Create a new job instance.
     */
    public function __construct(public Statement $statement) {}

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    /**
     * Execute the job: extract the statement with AI, persist the result and
     * reconcile the balances deterministically.
     */
    public function handle(StatementReconciler $reconciler, AnomalyDetector $detector): void
    {
        $this->statement->update(['status' => StatementStatus::Processing]);

        try {
            $response = (new StatementExtractor($this->customCategories()))->prompt(
                'Extrae todos los datos y movimientos de este estado de cuenta.',
                attachments: [
                    Document::fromStorage($this->statement->file_path, disk: 'local'),
                ],
                model: config('centavo.ai_model'),
            );

            $data = $this->decodeJson((string) $response->text);

            DB::transaction(function () use ($data, $reconciler, $detector): void {
                $this->persist($data);
                $reconciler->reconcile($this->statement);
                $detector->detect($this->statement);
            });

            if (config('centavo.delete_pdf_after_processing') && $this->statement->file_path) {
                Storage::disk('local')->delete($this->statement->file_path);
                $this->statement->update(['file_path' => null]);
            }
        } catch (Throwable $e) {
            $this->statement->update([
                'status' => StatementStatus::Failed,
                'failure_reason' => $e->getMessage(),
            ]);

            report($e);

            throw $e;
        }
    }

    /**
     * Decode the model's JSON reply into an array, tolerating any markdown
     * fences or stray prose around the object.
     *
     * @return array<string, mixed>
     */
    private function decodeJson(string $text): array
    {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');

        $data = ($start !== false && $end !== false && $end >= $start)
            ? json_decode(substr($text, $start, $end - $start + 1), true)
            : null;

        if (! is_array($data)) {
            // Log the full raw reply so we can inspect what the model actually
            // returned when parsing fails (truncation, preamble, trailing
            // commas, etc.). Internal only; the client sees a generic message.
            Log::error('StatementExtractor: no se pudo parsear la respuesta de la IA.', [
                'statement_id' => $this->statement->id,
                'response_length' => mb_strlen($text),
                'json_error' => json_last_error_msg(),
                'raw_response' => $text,
            ]);

            throw new RuntimeException('El extractor no devolvió un JSON válido.');
        }

        return $data;
    }

    /**
     * The custom category names the statement owner has already created, so the
     * AI can reuse them when classifying. This job runs without an authenticated
     * user, so it filters by the owner explicitly instead of via the global
     * scope.
     *
     * @return array<int, string>
     */
    private function customCategories(): array
    {
        return Transaction::query()
            ->whereRelation('account', 'user_id', $this->statement->account->user_id)
            ->whereNotNull('category')
            ->distinct()
            ->pluck('category')
            ->reject(fn (string $category): bool => TransactionCategory::tryFrom($category) !== null)
            ->values()
            ->all();
    }

    /**
     * Persist the extracted statement header and its transactions.
     *
     * @param  array<string, mixed>  $data
     */
    private function persist(array $data): void
    {
        $this->statement->update([
            'period_start' => $data['period_start'] ?? null,
            'period_end' => $data['period_end'] ?? null,
            'beginning_balance' => $data['beginning_balance'] ?? null,
            'ending_balance' => $data['ending_balance'] ?? null,
            'total_deposits' => $data['total_deposits'] ?? null,
            'total_withdrawals' => $data['total_withdrawals'] ?? null,
        ]);

        // Reset transactions so retries stay idempotent.
        $this->statement->transactions()->delete();

        $transactions = $data['transactions'] ?? [];

        if (! is_array($transactions)) {
            return;
        }

        $rows = [];

        foreach ($transactions as $transaction) {
            if (! is_array($transaction)) {
                continue;
            }

            $rows[] = [
                'account_id' => $this->statement->account_id,
                'date' => $transaction['date'],
                'description' => $transaction['description'],
                'amount' => abs((float) $transaction['amount']),
                'direction' => $transaction['direction'],
                'running_balance' => $transaction['running_balance'] ?? null,
                'reference' => $transaction['reference'] ?? null,
                'merchant' => $transaction['merchant'] ?? null,
                'category' => $transaction['category'] ?? null,
            ];
        }

        if ($rows !== []) {
            $this->statement->transactions()->createMany($rows);
        }
    }
}

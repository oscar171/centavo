<?php

namespace App\Jobs;

use App\Ai\Agents\StatementExtractor;
use App\Enums\StatementStatus;
use App\Models\Statement;
use App\Services\AnomalyDetector;
use App\Services\StatementReconciler;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Files\Document;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;
use Throwable;

class ProcessStatement implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 180;

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
            $response = (new StatementExtractor)->prompt(
                'Extrae todos los datos y movimientos de este estado de cuenta.',
                attachments: [
                    Document::fromStorage($this->statement->file_path, disk: 'local'),
                ],
                model: config('centavo.ai_model'),
            );

            if (! $response instanceof StructuredAgentResponse) {
                throw new RuntimeException('El extractor no devolvió una respuesta estructurada.');
            }

            $data = $response->toArray();

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

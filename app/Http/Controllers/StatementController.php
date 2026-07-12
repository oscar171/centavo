<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\StatementStatus;
use App\Enums\TransactionCategory;
use App\Http\Requests\ReprocessStatementRequest;
use App\Http\Requests\StoreStatementRequest;
use App\Jobs\ProcessStatement;
use App\Models\Account;
use App\Models\Statement;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StatementController extends Controller
{
    /**
     * Show the standalone upload page where the user picks an account and
     * uploads a statement PDF.
     */
    public function create(Request $request): Response
    {
        $accounts = $request->user()->accounts()->orderBy('name')->get()
            ->map(fn (Account $account): array => [
                'uuid' => $account->uuid,
                'name' => $account->name,
                'bank' => $account->bank,
            ]);

        $statements = Statement::query()
            ->with('account:id,name,bank')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Statement $statement): array => [
                'uuid' => $statement->uuid,
                'original_filename' => $statement->original_filename,
                'status' => $statement->status->value,
                'status_label' => $statement->status->label(),
                'account_name' => $statement->account->name,
                'bank' => $statement->account->bank,
                'period_start' => $statement->period_start?->toDateString(),
                'period_end' => $statement->period_end?->toDateString(),
            ]);

        return Inertia::render('statements/create', [
            'accounts' => $accounts,
            'statements' => $statements,
            'banks' => config('banks'),
            'accountTypes' => AccountType::options(),
        ]);
    }

    /**
     * Store an uploaded statement PDF and queue it for processing.
     */
    public function store(StoreStatementRequest $request, Account $account): RedirectResponse
    {
        $file = $request->file('file');

        $path = $file->store("statements/{$account->id}", 'local');

        $statement = $account->statements()->create([
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'status' => StatementStatus::Pending,
        ]);

        ProcessStatement::dispatch($statement);

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Procesando tu estado de cuenta…')]);

        return to_route('statements.show', $statement);
    }

    /**
     * Replace the statement PDF and queue it to be processed again. Useful when
     * a statement failed or needs review and the user wants to re-upload.
     */
    public function reprocess(ReprocessStatementRequest $request, Statement $statement): RedirectResponse
    {
        $file = $request->file('file');

        $path = $file->store("statements/{$statement->account_id}", 'local');

        if ($statement->file_path) {
            Storage::disk('local')->delete($statement->file_path);
        }

        $statement->update([
            'original_filename' => $file->getClientOriginalName(),
            'file_path' => $path,
            'status' => StatementStatus::Pending,
            'failure_reason' => null,
            'reconciliation_diff' => null,
            'is_reconciled' => false,
            'processed_at' => null,
        ]);

        ProcessStatement::dispatch($statement);

        Inertia::flash('toast', ['type' => 'info', 'message' => __('Reprocesando tu estado de cuenta…')]);

        return to_route('statements.show', $statement);
    }

    /**
     * Display a statement with its transactions and anomalies.
     */
    public function show(Statement $statement): Response
    {
        $this->authorize('view', $statement);

        $statement->load([
            'account',
            'transactions' => fn ($query) => $query->orderBy('date')->orderBy('id'),
            'anomalies' => fn ($query) => $query->orderByDesc('severity'),
        ]);

        $account = $statement->account;

        return Inertia::render('statements/show', [
            'statement' => [
                'uuid' => $statement->uuid,
                'original_filename' => $statement->original_filename,
                'status' => $statement->status->value,
                'status_label' => $statement->status->label(),
                'is_reconciled' => $statement->is_reconciled,
                'reconciliation_diff' => $statement->reconciliation_diff,
                'failure_reason' => $statement->failure_reason,
                'period_start' => $statement->period_start?->toDateString(),
                'period_end' => $statement->period_end?->toDateString(),
                'beginning_balance' => $statement->beginning_balance,
                'ending_balance' => $statement->ending_balance,
                'total_deposits' => $statement->total_deposits,
                'total_withdrawals' => $statement->total_withdrawals,
            ],
            'account' => [
                'id' => $account->id,
                'name' => $account->name,
                'bank' => $account->bank,
                'currency' => $account->currency,
            ],
            'transactions' => $statement->transactions->map(fn ($transaction): array => [
                'uuid' => $transaction->uuid,
                'date' => $transaction->date->toDateString(),
                'description' => $transaction->description,
                'merchant' => $transaction->merchant,
                'amount' => $transaction->amount,
                'direction' => $transaction->direction->value,
                'running_balance' => $transaction->running_balance,
                'category' => $transaction->category,
                'category_label' => TransactionCategory::labelFor($transaction->category),
            ]),
            'categoryOptions' => TransactionCategory::options(),
            'customCategories' => $this->customCategories(),
            'anomalies' => $statement->anomalies->map(fn ($anomaly): array => [
                'uuid' => $anomaly->uuid,
                'type' => $anomaly->type->value,
                'type_label' => $anomaly->type->label(),
                'severity' => $anomaly->severity->value,
                'severity_label' => $anomaly->severity->label(),
                'title' => $anomaly->title,
                'description' => $anomaly->description,
                'amount' => $anomaly->amount,
            ]),
        ]);
    }

    /**
     * The distinct custom category names the user has already created (i.e. any
     * stored category that is not one of the predefined ones), so they can be
     * reused from the category picker. Scoped to the user by the model's global
     * scope.
     *
     * @return array<int, string>
     */
    private function customCategories(): array
    {
        return Transaction::query()
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->reject(fn (string $category): bool => TransactionCategory::tryFrom($category) !== null)
            ->values()
            ->all();
    }
}

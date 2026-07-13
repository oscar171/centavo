<?php

namespace App\Http\Controllers;

use App\Enums\TransactionCategory;
use App\Enums\TransactionDirection;
use App\Http\Requests\UpdateTransactionCategoryRequest;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    /**
     * The category-filter sentinel for transactions without a category.
     */
    private const UNCATEGORIZED = 'uncategorized';

    /**
     * Display a paginated, filterable list of the user's transactions, newest
     * first. Filters (account, search, direction, category, amount and date
     * range) are applied server-side; the summary totals reflect the whole
     * filtered set, not just the current page.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        /** @var Collection<int, Account> $accounts */
        $accounts = $user->accounts()->orderBy('name')->get();

        $accountIds = $accounts->pluck('id')->all();
        $selectedAccount = $accounts->firstWhere('uuid', $request->string('account')->value());
        $scopeIds = $selectedAccount ? [$selectedAccount->id] : $accountIds;

        $filters = [
            'search' => trim($request->string('q')->value()),
            'direction' => $request->string('direction')->value(),
            'category' => $request->string('category')->value(),
            'min' => $request->string('min')->value(),
            'max' => $request->string('max')->value(),
            'from' => $request->string('from')->value(),
            'to' => $request->string('to')->value(),
        ];

        $filtered = fn (): Builder => Transaction::query()
            ->whereIn('account_id', $scopeIds)
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $term = '%'.$filters['search'].'%';
                $query->where(function (Builder $sub) use ($term): void {
                    $sub->where('description', 'like', $term)
                        ->orWhere('merchant', 'like', $term)
                        ->orWhere('category', 'like', $term);
                });
            })
            ->when(in_array($filters['direction'], ['credit', 'debit'], true), fn (Builder $query): Builder => $query->where('direction', $filters['direction']))
            ->when($filters['category'] === self::UNCATEGORIZED, fn (Builder $query): Builder => $query->whereNull('category'))
            ->when($filters['category'] !== '' && $filters['category'] !== self::UNCATEGORIZED, fn (Builder $query): Builder => $query->where('category', $filters['category']))
            ->when(is_numeric($filters['min']), fn (Builder $query): Builder => $query->where('amount', '>=', (float) $filters['min']))
            ->when(is_numeric($filters['max']), fn (Builder $query): Builder => $query->where('amount', '<=', (float) $filters['max']))
            ->when($filters['from'] !== '', fn (Builder $query): Builder => $query->where('date', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn (Builder $query): Builder => $query->where('date', '<=', $filters['to']));

        $income = (float) $filtered()->where('direction', TransactionDirection::Credit)->sum('amount');
        $expense = (float) $filtered()->where('direction', TransactionDirection::Debit)->sum('amount');

        $transactions = $filtered()
            ->with(['account:id,name,currency', 'statement:id,uuid'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Transaction $transaction): array => [
                'uuid' => $transaction->uuid,
                'date' => $transaction->date->toDateString(),
                'description' => $transaction->description,
                'merchant' => $transaction->merchant,
                'amount' => $transaction->amount,
                'direction' => $transaction->direction->value,
                'category' => $transaction->category,
                'category_label' => TransactionCategory::labelFor($transaction->category),
                'account_name' => $transaction->account->name,
                'currency' => $transaction->account->currency,
                'statement_uuid' => $transaction->statement->uuid,
            ]);

        return Inertia::render('transactions/index', [
            'transactions' => $transactions,
            'accounts' => $accounts->map(fn (Account $account): array => [
                'uuid' => $account->uuid,
                'name' => $account->name,
            ]),
            'selectedAccount' => $selectedAccount?->uuid,
            'currency' => $selectedAccount?->currency ?? $accounts->first()?->currency ?? 'USD',
            'filters' => $filters,
            'presentCategories' => $this->presentCategories($accountIds),
            'hasUncategorized' => Transaction::query()
                ->whereIn('account_id', $accountIds)
                ->whereNull('category')
                ->exists(),
            'summary' => [
                'income' => round($income, 2),
                'expense' => round($expense, 2),
                'net' => round($income - $expense, 2),
                'count' => $transactions->total(),
            ],
        ]);
    }

    /**
     * The distinct category values present across the user's accounts, shaped as
     * filter options with a display label (localized for predefined categories,
     * raw name for custom ones).
     *
     * @param  array<int, int>  $accountIds
     * @return array<int, array{value: string, label: string}>
     */
    private function presentCategories(array $accountIds): array
    {
        return Transaction::query()
            ->whereIn('account_id', $accountIds)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category')
            ->map(fn (string $category): array => [
                'value' => $category,
                'label' => TransactionCategory::labelFor($category) ?? $category,
            ])
            ->values()
            ->all();
    }

    /**
     * Update a transaction's category, optionally applying the same category to
     * every transaction from the same merchant across the user's accounts.
     */
    public function updateCategory(UpdateTransactionCategoryRequest $request, Transaction $transaction): RedirectResponse
    {
        $category = trim($request->string('category')->value());
        $applyToAll = $request->boolean('apply_to_all') && $transaction->merchant !== null;

        if ($applyToAll) {
            $accountIds = $request->user()->accounts()->pluck('id');

            Transaction::query()
                ->whereIn('account_id', $accountIds)
                ->where('merchant', $transaction->merchant)
                ->update(['category' => $category]);
        } else {
            $transaction->update(['category' => $category]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Categoría actualizada.')]);

        return back();
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\TransactionCategory;
use App\Http\Requests\UpdateTransactionCategoryRequest;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    /**
     * Display a paginated list of the user's transactions, newest first,
     * optionally scoped to a single account.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        /** @var Collection<int, Account> $accounts */
        $accounts = $user->accounts()->orderBy('name')->get();

        $selectedAccount = $accounts->firstWhere('uuid', $request->string('account')->value());

        $scopeIds = $selectedAccount
            ? [$selectedAccount->id]
            : $accounts->pluck('id')->all();

        $transactions = Transaction::query()
            ->whereIn('account_id', $scopeIds)
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
        ]);
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

<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Http\Requests\StoreAccountRequest;
use App\Models\Account;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Display the authenticated user's accounts.
     */
    public function index(): Response
    {
        $accounts = Account::query()
            ->where('user_id', auth()->id())
            ->withCount('statements')
            ->latest()
            ->get()
            ->map(fn (Account $account): array => [
                'uuid' => $account->uuid,
                'name' => $account->name,
                'bank' => $account->bank,
                'account_type' => $account->account_type?->value,
                'account_type_label' => $account->account_type?->label(),
                'last_four' => $account->last_four,
                'currency' => $account->currency,
                'statements_count' => $account->statements_count,
            ]);

        return Inertia::render('accounts/index', [
            'accounts' => $accounts,
            'banks' => config('banks'),
            'accountTypes' => AccountType::options(),
        ]);
    }

    /**
     * Store a newly created account for the authenticated user.
     */
    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $request->user()->accounts()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cuenta creada.')]);

        // Return to the page the account was created from (accounts list or the
        // upload page), so its account selector/list refreshes in place.
        return back();
    }

    /**
     * Display a single account with its statements.
     */
    public function show(Account $account): Response
    {
        $this->authorize('view', $account);

        $account->load(['statements' => fn ($query) => $query->latest()]);

        return Inertia::render('accounts/show', [
            'account' => [
                'uuid' => $account->uuid,
                'name' => $account->name,
                'bank' => $account->bank,
                'account_type' => $account->account_type?->value,
                'account_type_label' => $account->account_type?->label(),
                'last_four' => $account->last_four,
                'currency' => $account->currency,
            ],
            'statements' => $account->statements->map(fn ($statement): array => [
                'uuid' => $statement->uuid,
                'original_filename' => $statement->original_filename,
                'status' => $statement->status->value,
                'status_label' => $statement->status->label(),
                'period_start' => $statement->period_start?->toDateString(),
                'period_end' => $statement->period_end?->toDateString(),
                'created_at' => $statement->created_at?->toDateTimeString(),
            ]),
        ]);
    }

    /**
     * Remove the given account.
     */
    public function destroy(Account $account): RedirectResponse
    {
        $this->authorize('delete', $account);

        $account->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Cuenta eliminada.')]);

        return to_route('accounts.index');
    }
}

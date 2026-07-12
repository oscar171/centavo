<?php

namespace App\Services;

use App\Enums\StatementStatus;
use App\Enums\TransactionDirection;
use App\Models\Statement;

class StatementReconciler
{
    /**
     * Maximum acceptable rounding difference (in currency units).
     */
    private const TOLERANCE = 0.01;

    /**
     * Deterministically validate the extracted balances against the
     * transactions. This is the anti-error layer on top of the AI: if the
     * numbers do not add up, the statement is flagged for manual review.
     */
    public function reconcile(Statement $statement): void
    {
        $statement->loadMissing('transactions');

        $credits = (float) $statement->transactions
            ->where('direction', TransactionDirection::Credit)
            ->sum('amount');

        $debits = (float) $statement->transactions
            ->where('direction', TransactionDirection::Debit)
            ->sum('amount');

        $begin = (float) $statement->beginning_balance;
        $end = (float) $statement->ending_balance;

        $expectedEnd = $begin + $credits - $debits;
        $diff = round($expectedEnd - $end, 2);

        $balancesOk = abs($diff) <= self::TOLERANCE;
        $depositsOk = abs($credits - (float) $statement->total_deposits) <= self::TOLERANCE;
        $withdrawalsOk = abs($debits - (float) $statement->total_withdrawals) <= self::TOLERANCE;

        $reconciled = $balancesOk && $depositsOk && $withdrawalsOk;

        $statement->update([
            'is_reconciled' => $reconciled,
            'reconciliation_diff' => $diff,
            'status' => $reconciled ? StatementStatus::Processed : StatementStatus::NeedsReview,
            'processed_at' => now(),
        ]);
    }
}

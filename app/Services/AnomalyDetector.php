<?php

namespace App\Services;

use App\Enums\AnomalySeverity;
use App\Enums\AnomalyType;
use App\Enums\TransactionDirection;
use App\Models\Statement;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class AnomalyDetector
{
    /**
     * Minimum number of same-merchant, same-day debits to be a burst.
     */
    private const BURST_THRESHOLD = 5;

    /**
     * Keywords in a credit description that identify a reversal/refund.
     */
    private const REVERSAL_KEYWORDS = [
        'purchase return',
        'reversal',
        'provisional credit',
        'refund',
    ];

    /**
     * Run the deterministic anomaly rules over a statement's transactions and
     * persist the resulting anomalies. Idempotent: previously detected
     * anomalies for the statement are cleared first.
     */
    public function detect(Statement $statement): void
    {
        $statement->loadMissing('transactions');

        $statement->anomalies()->delete();

        /** @var Collection<int, Transaction> $debits */
        $debits = $statement->transactions
            ->where('direction', TransactionDirection::Debit)
            ->values();

        /** @var Collection<int, Transaction> $credits */
        $credits = $statement->transactions
            ->where('direction', TransactionDirection::Credit)
            ->values();

        $burstTransactionIds = $this->detectChargeBursts($statement, $debits);
        $this->detectDuplicateCharges($statement, $debits, $burstTransactionIds);
        $this->detectReversals($statement, $debits, $credits);
    }

    /**
     * Rule: 5+ debits to the same merchant on the same day.
     *
     * @param  Collection<int, Transaction>  $debits
     * @return array<int, int> the ids of transactions that belong to a burst
     */
    private function detectChargeBursts(Statement $statement, Collection $debits): array
    {
        $burstIds = [];

        $debits
            ->filter(fn (Transaction $transaction): bool => $transaction->merchant !== null)
            ->groupBy(fn (Transaction $transaction): string => $transaction->merchant.'|'.$transaction->date->toDateString())
            ->each(function (Collection $group) use ($statement, &$burstIds): void {
                if ($group->count() < self::BURST_THRESHOLD) {
                    return;
                }

                /** @var Transaction $first */
                $first = $group->first();
                $ids = $group->pluck('id')->all();
                $burstIds = array_merge($burstIds, $ids);
                $total = (float) $group->sum('amount');

                $this->createAnomaly(
                    $statement,
                    AnomalyType::ChargeBurst,
                    AnomalySeverity::High,
                    "Ráfaga de cargos en {$first->merchant}",
                    "{$group->count()} cargos a {$first->merchant} el {$first->date->toDateString()} por un total de ".number_format($total, 2).'.',
                    $total,
                    $ids,
                );
            });

        return $burstIds;
    }

    /**
     * Rule: the same merchant + amount + day appears more than once and is not
     * already part of a burst.
     *
     * @param  Collection<int, Transaction>  $debits
     * @param  array<int, int>  $burstTransactionIds
     */
    private function detectDuplicateCharges(Statement $statement, Collection $debits, array $burstTransactionIds): void
    {
        $debits
            ->reject(fn (Transaction $transaction): bool => in_array($transaction->id, $burstTransactionIds, true))
            ->filter(fn (Transaction $transaction): bool => $transaction->merchant !== null)
            ->groupBy(fn (Transaction $transaction): string => $transaction->merchant.'|'.$transaction->amount.'|'.$transaction->date->toDateString())
            ->each(function (Collection $group) use ($statement): void {
                if ($group->count() < 2) {
                    return;
                }

                /** @var Transaction $first */
                $first = $group->first();
                $ids = $group->pluck('id')->all();
                $total = (float) $group->sum('amount');

                $this->createAnomaly(
                    $statement,
                    AnomalyType::DuplicateCharge,
                    AnomalySeverity::Medium,
                    "Cargo duplicado en {$first->merchant}",
                    "{$group->count()} cargos idénticos de ".number_format((float) $first->amount, 2)." a {$first->merchant} el {$first->date->toDateString()}.",
                    $total,
                    $ids,
                );
            });
    }

    /**
     * Rule: a credit that is a return/reversal by description, or that matches a
     * previous debit by reference number.
     *
     * @param  Collection<int, Transaction>  $debits
     * @param  Collection<int, Transaction>  $credits
     */
    private function detectReversals(Statement $statement, Collection $debits, Collection $credits): void
    {
        $debitsByReference = $debits
            ->filter(fn (Transaction $transaction): bool => $transaction->reference !== null)
            ->keyBy('reference');

        foreach ($credits as $credit) {
            $matchedDebit = $credit->reference !== null
                ? $debitsByReference->get($credit->reference)
                : null;

            $matchesKeyword = Str::contains(
                Str::lower($credit->description),
                self::REVERSAL_KEYWORDS,
            );

            if (! $matchedDebit && ! $matchesKeyword) {
                continue;
            }

            $ids = array_values(array_filter([$credit->id, $matchedDebit?->id]));
            $merchant = $credit->merchant ?? 'un comercio';

            $this->createAnomaly(
                $statement,
                AnomalyType::Reversal,
                AnomalySeverity::Low,
                "Devolución de {$merchant}",
                'Se registró una devolución o crédito por '.number_format((float) $credit->amount, 2).($matchedDebit ? ' asociada a un cargo previo.' : '.'),
                (float) $credit->amount,
                $ids,
                $matchedDebit ? ['matched_reference' => $credit->reference] : [],
            );
        }
    }

    /**
     * Persist a single anomaly for the statement.
     *
     * @param  array<int, int>  $transactionIds
     * @param  array<string, mixed>  $metadata
     */
    private function createAnomaly(
        Statement $statement,
        AnomalyType $type,
        AnomalySeverity $severity,
        string $title,
        string $description,
        ?float $amount,
        array $transactionIds,
        array $metadata = [],
    ): void {
        $statement->anomalies()->create([
            'account_id' => $statement->account_id,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'description' => $description,
            'amount' => $amount,
            'transaction_ids' => $transactionIds,
            'metadata' => $metadata,
            'status' => 'open',
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\TransactionCategory;
use App\Enums\TransactionDirection;
use App\Models\Account;
use App\Models\Statement;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * The selectable date-range presets, mapped to a number of months. Twelve
     * months is the widest window and the default.
     *
     * @var array<string, int>
     */
    private const RANGES = ['1m' => 1, '3m' => 3, '6m' => 6, '12m' => 12];

    /**
     * The default date-range preset.
     */
    private const DEFAULT_RANGE = '12m';

    /**
     * How many categories get their own line before the tail is folded into an
     * "Otras" series.
     */
    private const CATEGORY_LINES = 6;

    /**
     * Show the dashboard: filter controls and headline KPIs load immediately,
     * while the heavier widgets (charts, statements) are deferred.
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

        $currency = 'USD';

        if ($selectedAccount) {
            $currency = $selectedAccount->currency;
        } else {
            $firstAccount = $accounts->first();

            if ($firstAccount) {
                $currency = $firstAccount->currency;
            }
        }

        $range = $request->string('range')->value();
        $range = array_key_exists($range, self::RANGES) ? $range : self::DEFAULT_RANGE;

        $window = $this->resolveWindow($scopeIds, $range);
        $from = $window['from'];
        $to = $window['to'];

        $summary = $this->sums($scopeIds, $from, $to);
        $previous = $this->sums($scopeIds, $window['prevFrom'], $window['prevTo']);

        $summaryChange = [
            'income' => $this->percentChange($summary['income'], $previous['income']),
            'expense' => $this->percentChange($summary['expense'], $previous['expense']),
            'net' => $this->percentChange($summary['net'], $previous['net']),
        ];

        return Inertia::render('dashboard', [
            'accounts' => $accounts->map(fn (Account $account): array => [
                'uuid' => $account->uuid,
                'name' => $account->name,
                'bank' => $account->bank,
            ]),
            'selectedAccount' => $selectedAccount?->uuid,
            'currency' => $currency,
            'range' => $range,
            'summary' => $summary,
            'summaryChange' => $summaryChange,
            'currentBalance' => $this->currentBalance($scopeIds),
            'monthly' => Inertia::defer(fn (): array => $this->monthlySeries($scopeIds, $from, $to), 'charts'),
            'spendingByCategory' => Inertia::defer(fn (): array => $this->spendingByCategory($scopeIds, $from, $to), 'charts'),
            'recentStatements' => Inertia::defer(fn (): array => $this->recentStatements($scopeIds, $from, $to), 'activity'),
        ]);
    }

    /**
     * Resolve the active date window and the immediately preceding window used
     * for period-over-period comparison. The window is anchored to the user's
     * most recent transaction so historical data is always visible.
     *
     * @param  array<int, int>  $scopeIds
     * @return array{from: Carbon, to: Carbon, prevFrom: Carbon, prevTo: Carbon}
     */
    private function resolveWindow(array $scopeIds, string $range): array
    {
        $months = self::RANGES[$range];

        $latest = Transaction::whereIn('account_id', $scopeIds)->max('date');
        $anchor = $latest ? Carbon::parse($latest) : Carbon::now();

        $to = $anchor->copy()->endOfMonth();
        $from = $anchor->copy()->startOfMonth()->subMonths($months - 1);
        $prevTo = $from->copy()->subDay();
        $prevFrom = $from->copy()->subMonths($months);

        return ['from' => $from, 'to' => $to, 'prevFrom' => $prevFrom, 'prevTo' => $prevTo];
    }

    /**
     * Sum credits, debits and the net for a window.
     *
     * @param  array<int, int>  $scopeIds
     * @return array{income: float, expense: float, net: float}
     */
    private function sums(array $scopeIds, Carbon $from, Carbon $to): array
    {
        $base = fn (): Builder => Transaction::query()
            ->whereIn('account_id', $scopeIds)
            ->where('date', '>=', $from->toDateString())
            ->where('date', '<=', $to->toDateString());

        $income = (float) $base()->where('direction', TransactionDirection::Credit)->sum('amount');
        $expense = (float) $base()->where('direction', TransactionDirection::Debit)->sum('amount');

        return [
            'income' => $income,
            'expense' => $expense,
            'net' => round($income - $expense, 2),
        ];
    }

    /**
     * The current balance: the ending balance of the most recent statement per
     * account in scope, summed. Independent of the selected date range since it
     * reflects the latest known balance, not the period's activity. Returns null
     * when no statement has an ending balance yet.
     *
     * @param  array<int, int>  $scopeIds
     */
    private function currentBalance(array $scopeIds): ?float
    {
        if ($scopeIds === []) {
            return null;
        }

        $latestPerAccount = Statement::query()
            ->whereIn('account_id', $scopeIds)
            ->whereNotNull('ending_balance')
            ->whereNotNull('period_end')
            ->orderBy('account_id')
            ->orderByDesc('period_end')
            ->orderByDesc('id')
            ->get(['account_id', 'ending_balance'])
            ->groupBy('account_id')
            ->map(fn ($group): float => (float) $group->first()->ending_balance);

        return $latestPerAccount->isEmpty() ? null : round($latestPerAccount->sum(), 2);
    }

    /**
     * Percentage change from a previous value. Returns null when there is no
     * baseline to compare against.
     */
    private function percentChange(float $current, float $previous): ?float
    {
        if ($previous === 0.0) {
            return null;
        }

        return round(($current - $previous) / abs($previous) * 100, 1);
    }

    /**
     * Build a per-month income/expense series with no gaps between the first
     * and last month that hold data.
     *
     * @param  array<int, int>  $scopeIds
     * @return array<int, array{month: string, label: string, income: float, expense: float}>
     */
    private function monthlySeries(array $scopeIds, Carbon $from, Carbon $to): array
    {
        $rows = Transaction::query()
            ->whereIn('account_id', $scopeIds)
            ->where('date', '>=', $from->toDateString())
            ->where('date', '<=', $to->toDateString())
            ->get(['date', 'amount', 'direction']);

        $byMonth = [];

        foreach ($rows as $row) {
            $key = $row->date->format('Y-m');
            $byMonth[$key] ??= ['income' => 0.0, 'expense' => 0.0];

            if ($row->direction === TransactionDirection::Credit) {
                $byMonth[$key]['income'] += (float) $row->amount;
            } else {
                $byMonth[$key]['expense'] += (float) $row->amount;
            }
        }

        return array_map(fn (array $bucket): array => [
            'month' => $bucket['month'],
            'label' => $bucket['label'],
            'income' => round($byMonth[$bucket['month']]['income'] ?? 0.0, 2),
            'expense' => round($byMonth[$bucket['month']]['expense'] ?? 0.0, 2),
        ], $this->monthBuckets(array_keys($byMonth)));
    }

    /**
     * Spending per category across each month in the window, shaped for a
     * multi-line time series. Only the top categories get their own line; the
     * long tail is folded into an "Otras" series so the chart stays readable.
     * Custom categories are kept as-is; predefined ones are localized. The raw
     * category value travels with each line so the client can color it.
     *
     * @param  array<int, int>  $scopeIds
     * @return array{
     *     categories: array<int, array{key: string, value: string|null, name: string, total: float}>,
     *     series: array<int, array<string, float|string>>
     * }
     */
    private function spendingByCategory(array $scopeIds, Carbon $from, Carbon $to): array
    {
        $rows = Transaction::query()
            ->whereIn('account_id', $scopeIds)
            ->where('direction', TransactionDirection::Debit)
            ->where('date', '>=', $from->toDateString())
            ->where('date', '<=', $to->toDateString())
            ->get(['date', 'amount', 'category']);

        if ($rows->isEmpty()) {
            return ['categories' => [], 'series' => []];
        }

        // Rank categories by total spend to decide which get their own line.
        // The empty-string key holds uncategorized spend.
        $totals = [];
        $valueFor = [];

        foreach ($rows as $row) {
            $catKey = $row->category ?? '';
            $totals[$catKey] = ($totals[$catKey] ?? 0.0) + (float) $row->amount;
            $valueFor[$catKey] = $row->category;
        }

        arsort($totals);

        $topKeys = array_slice(array_keys($totals), 0, self::CATEGORY_LINES);
        $hasOther = count($totals) > self::CATEGORY_LINES;

        // Assign a stable, dot-free series key to each category (custom names may
        // contain dots, which recharts would treat as nested-path accessors).
        /** @var array<string, string> $keyFor */
        $keyFor = [];
        $categories = [];

        foreach ($topKeys as $index => $catKey) {
            $key = 'c'.$index;
            $keyFor[$catKey] = $key;
            $categories[] = [
                'key' => $key,
                'value' => $valueFor[$catKey],
                'name' => TransactionCategory::labelFor($valueFor[$catKey]) ?? 'Sin categoría',
                'total' => round($totals[$catKey], 2),
            ];
        }

        if ($hasOther) {
            $otherTotal = 0.0;

            foreach ($totals as $catKey => $total) {
                if (! array_key_exists($catKey, $keyFor)) {
                    $otherTotal += $total;
                }
            }

            $categories[] = ['key' => 'other', 'value' => null, 'name' => 'Otras', 'total' => round($otherTotal, 2)];
        }

        // Accumulate spend per month per series key.
        $byMonth = [];

        foreach ($rows as $row) {
            $monthKey = $row->date->format('Y-m');
            $seriesKey = $keyFor[$row->category ?? ''] ?? 'other';
            $byMonth[$monthKey][$seriesKey] = ($byMonth[$monthKey][$seriesKey] ?? 0.0) + (float) $row->amount;
        }

        $seriesKeys = array_map(fn (array $category): string => $category['key'], $categories);

        $series = array_map(function (array $bucket) use ($byMonth, $seriesKeys): array {
            $point = ['month' => $bucket['month'], 'label' => $bucket['label']];

            foreach ($seriesKeys as $key) {
                $point[$key] = round($byMonth[$bucket['month']][$key] ?? 0.0, 2);
            }

            return $point;
        }, $this->monthBuckets(array_keys($byMonth)));

        return ['categories' => $categories, 'series' => $series];
    }

    /**
     * A gap-free, chronological list of month buckets spanning the given month
     * keys (format "Y-m"). Returns an empty array when no keys are present.
     *
     * @param  array<int, string>  $monthKeys
     * @return array<int, array{month: string, label: string}>
     */
    private function monthBuckets(array $monthKeys): array
    {
        if ($monthKeys === []) {
            return [];
        }

        sort($monthKeys);

        $labels = ['', 'Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

        $cursor = Carbon::createFromFormat('Y-m', $monthKeys[0])->startOfMonth();
        $end = Carbon::createFromFormat('Y-m', (string) end($monthKeys))->startOfMonth();

        $buckets = [];

        while ($cursor->lessThanOrEqualTo($end)) {
            $buckets[] = [
                'month' => $cursor->format('Y-m'),
                'label' => $labels[(int) $cursor->format('n')].' '.$cursor->format('Y'),
            ];

            $cursor->addMonth();
        }

        return $buckets;
    }

    /**
     * The most recently uploaded statements within the window.
     *
     * @param  array<int, int>  $scopeIds
     * @return array<int, array<string, mixed>>
     */
    private function recentStatements(array $scopeIds, Carbon $from, Carbon $to): array
    {
        return Statement::query()
            ->whereIn('account_id', $scopeIds)
            ->with('account:id,name,bank')
            ->where(function (Builder $sub) use ($from, $to): void {
                $sub->whereNull('period_end')
                    ->orWhere(fn (Builder $period) => $period
                        ->where('period_end', '>=', $from->toDateString())
                        ->where('period_start', '<=', $to->toDateString()));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(6)
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
                'total_deposits' => $statement->total_deposits,
                'total_withdrawals' => $statement->total_withdrawals,
            ])
            ->all();
    }
}

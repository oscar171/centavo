import { Deferred, Head, Link, router } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowUpRight,
    FileText,
    TrendingDown,
    TrendingUp,
    Wallet,
} from 'lucide-react';
import StatementController from '@/actions/App/Http/Controllers/StatementController';
import MonthlyChart from '@/components/monthly-chart';
import PageHeader from '@/components/page-header';
import SpendingLineChart from '@/components/spending-line-chart';
import StatementStatusBadge from '@/components/statement-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { formatCurrency, formatDate } from '@/lib/format';
import { dashboard } from '@/routes';
import { index as accountsIndex } from '@/routes/accounts';

type AccountOption = { uuid: string; name: string; bank: string };

type MerchantLine = { key: string; name: string; total: number };

type SpendingSeries = {
    merchants: MerchantLine[];
    series: Array<{ month: string; label: string } & Record<string, number>>;
};

type MonthlyPoint = {
    month: string;
    label: string;
    income: number;
    expense: number;
};

type RecentStatement = {
    uuid: string;
    original_filename: string;
    status: string;
    status_label: string;
    account_name: string;
    bank: string;
    period_start: string | null;
    period_end: string | null;
    total_deposits: string | null;
    total_withdrawals: string | null;
};

type SummaryChange = {
    income: number | null;
    expense: number | null;
    net: number | null;
};

type PageProps = {
    accounts: AccountOption[];
    selectedAccount: string | null;
    currency: string;
    range: string;
    summary: { income: number; expense: number; net: number };
    summaryChange: SummaryChange;
    // Deferred props (undefined until their follow-up request resolves).
    monthly?: MonthlyPoint[];
    spendingByMerchant?: SpendingSeries;
    recentStatements?: RecentStatement[];
};

const ALL_ACCOUNTS = 'all';

const DEFAULT_RANGE = '12m';

const RANGES = [
    { value: '12m', label: '12M' },
    { value: '6m', label: '6M' },
    { value: '3m', label: '3M' },
    { value: '1m', label: '1M' },
];

function SummaryCard({
    label,
    value,
    accent,
    change,
    positiveIsGood,
    icon: Icon,
}: {
    label: string;
    value: string;
    accent?: 'brand' | 'negative';
    change: number | null;
    positiveIsGood: boolean;
    icon: typeof ArrowUpRight;
}) {
    const showDelta = change !== null && change !== 0;
    const rising = change !== null && change > 0;
    const favorable = change !== null && rising === positiveIsGood;
    const valueColor =
        accent === 'brand'
            ? 'text-brand'
            : accent === 'negative'
              ? 'text-negative'
              : '';

    return (
        <Card>
            <CardContent className="space-y-1 py-4">
                <div className="flex items-center gap-2 text-muted-foreground">
                    <Icon className="size-4" />
                    <span className="text-xs tracking-wide uppercase">
                        {label}
                    </span>
                </div>
                <div className="flex items-baseline gap-2">
                    <p
                        className={`text-2xl font-semibold tabular-nums ${valueColor}`}
                    >
                        {value}
                    </p>
                    {showDelta && (
                        <span
                            className={`flex items-center gap-0.5 text-xs tabular-nums ${favorable ? 'text-brand' : 'text-negative'}`}
                        >
                            {rising ? (
                                <TrendingUp className="size-3" />
                            ) : (
                                <TrendingDown className="size-3" />
                            )}
                            {Math.abs(change)}%
                        </span>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}

function ChartSkeleton() {
    return (
        <div className="flex h-65 items-end gap-2">
            {Array.from({ length: 8 }).map((_, index) => (
                <Skeleton
                    key={index}
                    className="flex-1"
                    style={{ height: `${30 + ((index * 37) % 60)}%` }}
                />
            ))}
        </div>
    );
}

function ListSkeleton() {
    return (
        <Card>
            <CardContent className="space-y-3 py-4">
                {Array.from({ length: 4 }).map((_, index) => (
                    <div
                        key={index}
                        className="flex items-center justify-between gap-4"
                    >
                        <Skeleton className="h-4 w-40" />
                        <Skeleton className="h-4 w-16" />
                    </div>
                ))}
            </CardContent>
        </Card>
    );
}

export default function Dashboard({
    accounts,
    selectedAccount,
    currency,
    range,
    summary,
    summaryChange,
    monthly,
    spendingByMerchant,
    recentStatements,
}: PageProps) {
    const applyFilters = ({
        range: nextRange,
        account: nextAccount,
    }: {
        range?: string;
        account?: string | null;
    }) => {
        const resolvedRange = nextRange ?? range;
        const resolvedAccount =
            nextAccount !== undefined ? nextAccount : (selectedAccount ?? null);

        const params: Record<string, string> = {};

        if (resolvedRange && resolvedRange !== DEFAULT_RANGE) {
            params.range = resolvedRange;
        }

        if (resolvedAccount) {
            params.account = resolvedAccount;
        }

        router.get(dashboard().url, params, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    if (accounts.length === 0) {
        return (
            <>
                <Head title="Dashboard" />
                <div className="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                    <PageHeader
                        title="Dashboard"
                        description="Un resumen de tus finanzas."
                    />
                    <div className="flex flex-1 flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border p-12 text-center">
                        <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                            <Wallet className="size-6 text-muted-foreground" />
                        </div>
                        <div>
                            <p className="font-medium">Aún no hay datos</p>
                            <p className="text-sm text-muted-foreground">
                                Crea una cuenta y sube un estado de cuenta para
                                ver tu resumen.
                            </p>
                        </div>
                        <Button asChild>
                            <Link href={accountsIndex()}>Ir a Cuentas</Link>
                        </Button>
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Dashboard" />

            <div className="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Dashboard"
                    description="Un resumen de tus ingresos, gastos y alertas."
                    action={
                        accounts.length > 1 ? (
                            <Select
                                value={selectedAccount ?? ALL_ACCOUNTS}
                                onValueChange={(value) =>
                                    applyFilters({
                                        account:
                                            value === ALL_ACCOUNTS
                                                ? null
                                                : value,
                                    })
                                }
                            >
                                <SelectTrigger className="w-56">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value={ALL_ACCOUNTS}>
                                        Todas las cuentas
                                    </SelectItem>
                                    {accounts.map((account) => (
                                        <SelectItem
                                            key={account.uuid}
                                            value={account.uuid}
                                        >
                                            {account.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        ) : undefined
                    }
                />

                <ToggleGroup
                    type="single"
                    size="sm"
                    value={range}
                    onValueChange={(value) =>
                        value && applyFilters({ range: value })
                    }
                    className="w-fit gap-1 rounded-lg bg-muted p-1"
                >
                    {RANGES.map((preset) => (
                        <ToggleGroupItem
                            key={preset.value}
                            value={preset.value}
                            aria-label={`Últimos ${preset.label}`}
                            className="rounded-md border-0! px-3 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground data-[state=on]:bg-background data-[state=on]:text-foreground data-[state=on]:shadow-sm"
                        >
                            {preset.label}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>

                <div className="grid gap-4 md:grid-cols-3">
                    <SummaryCard
                        label="Ingresos"
                        value={formatCurrency(summary.income, currency)}
                        accent="brand"
                        change={summaryChange.income}
                        positiveIsGood
                        icon={ArrowUpRight}
                    />
                    <SummaryCard
                        label="Gastos"
                        value={formatCurrency(summary.expense, currency)}
                        accent="negative"
                        change={summaryChange.expense}
                        positiveIsGood={false}
                        icon={ArrowDownRight}
                    />
                    <SummaryCard
                        label="Neto"
                        value={formatCurrency(summary.net, currency)}
                        accent={summary.net >= 0 ? 'brand' : 'negative'}
                        change={summaryChange.net}
                        positiveIsGood
                        icon={Wallet}
                    />
                </div>

                <div className="space-y-3">
                    <h2 className="text-lg font-medium">
                        Ingresos vs. gastos por mes
                    </h2>
                    <Card>
                        <CardContent className="py-4">
                            <Deferred
                                data="monthly"
                                fallback={<ChartSkeleton />}
                            >
                                {(monthly ?? []).length === 0 ? (
                                    <p className="py-8 text-center text-sm text-muted-foreground">
                                        Sin datos para el periodo seleccionado.
                                    </p>
                                ) : (
                                    <MonthlyChart
                                        data={monthly ?? []}
                                        currency={currency}
                                    />
                                )}
                            </Deferred>
                        </CardContent>
                    </Card>
                </div>

                <div className="space-y-3">
                    <h2 className="text-lg font-medium">Gasto por comercio</h2>
                    <Card>
                        <CardContent className="py-4">
                            <Deferred
                                data="spendingByMerchant"
                                fallback={<ChartSkeleton />}
                            >
                                {(spendingByMerchant?.series ?? []).length ===
                                0 ? (
                                    <p className="py-8 text-center text-sm text-muted-foreground">
                                        Sin gastos en el periodo.
                                    </p>
                                ) : (
                                    <SpendingLineChart
                                        merchants={
                                            spendingByMerchant?.merchants ?? []
                                        }
                                        series={
                                            spendingByMerchant?.series ?? []
                                        }
                                        currency={currency}
                                    />
                                )}
                            </Deferred>
                        </CardContent>
                    </Card>
                </div>

                <div className="space-y-3">
                    <h2 className="text-lg font-medium">Estados de cuenta</h2>

                    <Deferred
                        data="recentStatements"
                        fallback={<ListSkeleton />}
                    >
                        {(recentStatements ?? []).length === 0 ? (
                            <div className="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                                Aún no hay estados de cuenta en el periodo.
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {(recentStatements ?? []).map((statement) => (
                                    <Link
                                        key={statement.uuid}
                                        href={StatementController.show(
                                            statement.uuid,
                                        )}
                                        className="block rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                    >
                                        <Card className="transition-colors hover:border-foreground/20">
                                            <CardContent className="flex items-center justify-between gap-4 py-4">
                                                <div className="flex min-w-0 items-center gap-3">
                                                    <FileText className="size-5 shrink-0 text-muted-foreground" />
                                                    <div className="min-w-0">
                                                        <div className="flex items-center gap-2">
                                                            <p className="truncate font-medium">
                                                                {
                                                                    statement.original_filename
                                                                }
                                                            </p>
                                                            <Badge
                                                                variant="secondary"
                                                                className="shrink-0"
                                                            >
                                                                {statement.bank}
                                                            </Badge>
                                                        </div>
                                                        <p className="text-xs text-muted-foreground">
                                                            {statement.period_start &&
                                                            statement.period_end
                                                                ? `${formatDate(statement.period_start)} — ${formatDate(statement.period_end)} · ${statement.account_name}`
                                                                : statement.account_name}
                                                        </p>
                                                    </div>
                                                </div>
                                                <StatementStatusBadge
                                                    status={statement.status}
                                                    label={
                                                        statement.status_label
                                                    }
                                                />
                                            </CardContent>
                                        </Card>
                                    </Link>
                                ))}
                            </div>
                        )}
                    </Deferred>
                </div>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
};

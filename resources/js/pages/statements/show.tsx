import { Head, usePoll } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowDownLeft,
    ArrowUpRight,
    Search,
    TriangleAlert,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import CategoryBadge, { categoryColor } from '@/components/category-badge';
import PageHeader from '@/components/page-header';
import StatementReuploadDialog from '@/components/statement-reupload-dialog';
import StatementStatusBadge from '@/components/statement-status-badge';
import TransactionCategoryDialog from '@/components/transaction-category-dialog';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { formatCurrency, formatDate } from '@/lib/format';
import { index as accountsIndex } from '@/routes/accounts';

type Statement = {
    id: number;
    uuid: string;
    original_filename: string;
    status: string;
    status_label: string;
    is_reconciled: boolean;
    reconciliation_diff: string | null;
    failure_reason: string | null;
    period_start: string | null;
    period_end: string | null;
    beginning_balance: string | null;
    ending_balance: string | null;
    total_deposits: string | null;
    total_withdrawals: string | null;
};

type Account = {
    id: number;
    name: string;
    bank: string;
    currency: string;
};

type Transaction = {
    uuid: string;
    date: string | null;
    description: string;
    merchant: string | null;
    amount: string;
    direction: string;
    running_balance: string | null;
    category: string | null;
    category_label: string | null;
};

type CategoryOption = { value: string; label: string };

type CategoryFilterOption = { value: string; label: string };

type Anomaly = {
    uuid: string;
    type: string;
    type_label: string;
    severity: string;
    severity_label: string;
    title: string;
    description: string;
    amount: string | null;
};

type PageProps = {
    statement: Statement;
    account: Account;
    transactions: Transaction[];
    categoryOptions: CategoryOption[];
    customCategories: string[];
    anomalies: Anomaly[];
};

const severityBadge: Record<string, string> = {
    high: 'bg-destructive/10 text-destructive',
    medium: 'bg-amber-500/10 text-amber-600 dark:text-amber-400',
    low: 'bg-muted text-muted-foreground',
};

const UNCATEGORIZED = 'uncategorized';

function Kpi({
    label,
    value,
    accent,
    hint,
}: {
    label: string;
    value: string;
    accent?: 'brand' | 'negative';
    hint?: string;
}) {
    const valueColor =
        accent === 'brand'
            ? 'text-brand'
            : accent === 'negative'
              ? 'text-negative'
              : '';

    return (
        <Card>
            <CardContent className="space-y-1 py-4">
                <div className="flex items-center justify-between gap-2">
                    <p className="text-xs tracking-wide text-muted-foreground uppercase">
                        {label}
                    </p>
                    {hint && (
                        <span className="text-[10px] text-muted-foreground">
                            {hint}
                        </span>
                    )}
                </div>
                <p
                    className={`text-2xl font-semibold tabular-nums ${valueColor}`}
                >
                    {value}
                </p>
            </CardContent>
        </Card>
    );
}

function MovementsTab({
    filtered,
    totalCount,
    search,
    onSearchChange,
    categoryFilter,
    onCategoryFilterChange,
    presentCategories,
    hasUncategorized,
    categoryOptions,
    customCategories,
    currency,
}: {
    filtered: Transaction[];
    totalCount: number;
    search: string;
    onSearchChange: (value: string) => void;
    categoryFilter: string;
    onCategoryFilterChange: (value: string) => void;
    presentCategories: CategoryFilterOption[];
    hasUncategorized: boolean;
    categoryOptions: CategoryOption[];
    customCategories: string[];
    currency: string;
}) {
    return (
        <div className="space-y-4">
            <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div className="relative w-full sm:max-w-xs">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                    <Input
                        value={search}
                        onChange={(event) => onSearchChange(event.target.value)}
                        placeholder="Buscar por descripción o categoría"
                        className="pl-9"
                    />
                </div>
                <Select
                    value={categoryFilter}
                    onValueChange={onCategoryFilterChange}
                >
                    <SelectTrigger className="w-full sm:w-56">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">
                            Todas las categorías
                        </SelectItem>
                        {presentCategories.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                        {hasUncategorized && (
                            <SelectItem value={UNCATEGORIZED}>
                                Sin categoría
                            </SelectItem>
                        )}
                    </SelectContent>
                </Select>
            </div>

            <p className="text-xs text-muted-foreground tabular-nums">
                {filtered.length === totalCount
                    ? `${totalCount} movimientos`
                    : `${filtered.length} de ${totalCount} movimientos`}
            </p>

            {filtered.length === 0 ? (
                <div className="rounded-xl border border-dashed border-border p-12 text-center text-sm text-muted-foreground">
                    No hay movimientos que coincidan con el filtro.
                </div>
            ) : (
                <div className="rounded-xl border border-border">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-10">
                                    <span className="sr-only">Tipo</span>
                                </TableHead>
                                <TableHead>Fecha</TableHead>
                                <TableHead>Descripción</TableHead>
                                <TableHead>Categoría</TableHead>
                                <TableHead className="text-right">
                                    Monto
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {filtered.map((transaction) => (
                                <TableRow key={transaction.uuid}>
                                    <TableCell className="align-top">
                                        <span
                                            className={`flex size-7 items-center justify-center rounded-full ${
                                                transaction.direction ===
                                                'credit'
                                                    ? 'bg-brand/10 text-brand'
                                                    : 'bg-negative/10 text-negative'
                                            }`}
                                            title={
                                                transaction.direction ===
                                                'credit'
                                                    ? 'Depósito'
                                                    : 'Retiro'
                                            }
                                        >
                                            {transaction.direction ===
                                            'credit' ? (
                                                <ArrowDownLeft className="size-4" />
                                            ) : (
                                                <ArrowUpRight className="size-4" />
                                            )}
                                        </span>
                                    </TableCell>
                                    <TableCell className="align-top text-muted-foreground tabular-nums">
                                        {formatDate(transaction.date)}
                                    </TableCell>
                                    <TableCell className="max-w-xs align-top">
                                        <p className="font-medium">
                                            {transaction.merchant ??
                                                transaction.description}
                                        </p>
                                        {transaction.merchant && (
                                            <p className="truncate text-xs text-muted-foreground">
                                                {transaction.description}
                                            </p>
                                        )}
                                    </TableCell>
                                    <TableCell className="align-top">
                                        <div className="flex flex-col items-start gap-1">
                                            <CategoryBadge
                                                value={transaction.category}
                                                label={
                                                    transaction.category_label
                                                }
                                            />
                                            <TransactionCategoryDialog
                                                transaction={{
                                                    uuid: transaction.uuid,
                                                    merchant:
                                                        transaction.merchant,
                                                    description:
                                                        transaction.description,
                                                    category:
                                                        transaction.category,
                                                }}
                                                categoryOptions={
                                                    categoryOptions
                                                }
                                                customCategories={
                                                    customCategories
                                                }
                                            />
                                        </div>
                                    </TableCell>
                                    <TableCell className="text-right align-top tabular-nums">
                                        <span
                                            className={
                                                transaction.direction ===
                                                'credit'
                                                    ? 'text-brand'
                                                    : 'text-negative'
                                            }
                                        >
                                            {transaction.direction === 'credit'
                                                ? '+'
                                                : '−'}
                                            {formatCurrency(
                                                transaction.amount,
                                                currency,
                                            )}
                                        </span>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            )}
        </div>
    );
}

type CategoryRow = {
    value: string | null;
    label: string;
    total: number;
    count: number;
};

function aggregateByCategory(
    transactions: Transaction[],
    direction: string,
): { rows: CategoryRow[]; total: number } {
    const map = new Map<string, CategoryRow>();

    for (const transaction of transactions) {
        if (transaction.direction !== direction) {
            continue;
        }

        const key = transaction.category ?? UNCATEGORIZED;
        const current = map.get(key) ?? {
            value: transaction.category,
            label: transaction.category_label ?? 'Sin categoría',
            total: 0,
            count: 0,
        };

        current.total += Number(transaction.amount);
        current.count += 1;
        map.set(key, current);
    }

    const rows = [...map.values()].sort((a, b) => b.total - a.total);
    const total = rows.reduce((sum, row) => sum + row.total, 0);

    return { rows, total };
}

function CategoryGroup({
    title,
    rows,
    total,
    currency,
    totalAccent,
}: {
    title: string;
    rows: CategoryRow[];
    total: number;
    currency: string;
    totalAccent: string;
}) {
    return (
        <Card>
            <CardContent className="space-y-4 py-5">
                <div className="flex items-center justify-between gap-3">
                    <h3 className="text-sm font-medium">{title}</h3>
                    <span
                        className={`text-sm font-semibold tabular-nums ${totalAccent}`}
                    >
                        {formatCurrency(total, currency)}
                    </span>
                </div>

                {rows.map((row) => {
                    const pct = total > 0 ? (row.total / total) * 100 : 0;

                    return (
                        <div key={row.label} className="space-y-1.5">
                            <div className="flex items-center justify-between gap-3 text-sm">
                                <span className="flex min-w-0 items-center gap-2">
                                    <span
                                        className="size-2.5 shrink-0 rounded-full"
                                        style={{
                                            backgroundColor: categoryColor(
                                                row.value,
                                            ),
                                        }}
                                    />
                                    <span className="truncate font-medium">
                                        {row.label}
                                    </span>
                                    <span className="shrink-0 text-xs text-muted-foreground">
                                        {row.count}
                                    </span>
                                </span>
                                <span className="shrink-0 tabular-nums">
                                    {formatCurrency(row.total, currency)}
                                    <span className="ml-1.5 text-xs text-muted-foreground">
                                        {Math.round(pct)}%
                                    </span>
                                </span>
                            </div>
                            <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                <div
                                    className="h-full rounded-full"
                                    style={{
                                        width: `${pct}%`,
                                        backgroundColor: categoryColor(
                                            row.value,
                                        ),
                                    }}
                                />
                            </div>
                        </div>
                    );
                })}
            </CardContent>
        </Card>
    );
}

function CategoriesTab({
    transactions,
    currency,
}: {
    transactions: Transaction[];
    currency: string;
}) {
    const income = useMemo(
        () => aggregateByCategory(transactions, 'credit'),
        [transactions],
    );
    const expenses = useMemo(
        () => aggregateByCategory(transactions, 'debit'),
        [transactions],
    );

    if (income.rows.length === 0 && expenses.rows.length === 0) {
        return (
            <div className="rounded-xl border border-dashed border-border p-12 text-center text-sm text-muted-foreground">
                No hay movimientos categorizados en este estado de cuenta.
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {income.rows.length > 0 && (
                <CategoryGroup
                    title="Ingresos"
                    rows={income.rows}
                    total={income.total}
                    currency={currency}
                    totalAccent="text-brand"
                />
            )}
            {expenses.rows.length > 0 && (
                <CategoryGroup
                    title="Egresos"
                    rows={expenses.rows}
                    total={expenses.total}
                    currency={currency}
                    totalAccent="text-negative"
                />
            )}
        </div>
    );
}

function AnomaliesTab({
    anomalies,
    currency,
}: {
    anomalies: Anomaly[];
    currency: string;
}) {
    if (anomalies.length === 0) {
        return (
            <div className="rounded-xl border border-dashed border-border p-12 text-center text-sm text-muted-foreground">
                No se detectaron anomalías en este estado de cuenta.
            </div>
        );
    }

    return (
        <div className="space-y-2">
            {anomalies.map((anomaly) => (
                <Card key={anomaly.uuid}>
                    <CardContent className="flex items-start justify-between gap-4 py-4">
                        <div className="flex items-start gap-3">
                            <AlertTriangle className="mt-0.5 size-5 text-muted-foreground" />
                            <div className="space-y-0.5">
                                <p className="font-medium">{anomaly.title}</p>
                                <p className="text-sm text-muted-foreground">
                                    {anomaly.description}
                                </p>
                            </div>
                        </div>
                        <div className="flex flex-col items-end gap-1">
                            <span
                                className={`rounded-md px-2 py-0.5 text-xs font-medium ${severityBadge[anomaly.severity] ?? severityBadge.low}`}
                            >
                                {anomaly.severity_label}
                            </span>
                            {anomaly.amount && (
                                <span className="text-sm tabular-nums">
                                    {formatCurrency(anomaly.amount, currency)}
                                </span>
                            )}
                        </div>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}

export default function StatementShow({
    statement,
    account,
    transactions,
    categoryOptions,
    customCategories,
    anomalies,
}: PageProps) {
    const isInProgress =
        statement.status === 'pending' || statement.status === 'processing';

    // Refresh the page while the queue processes the statement.
    usePoll(4000, {}, { autoStart: isInProgress });

    const currency = account.currency;

    // Filter state is lifted here so the summary cards react to it too.
    const [search, setSearch] = useState('');
    const [categoryFilter, setCategoryFilter] = useState('all');

    const presentCategories = useMemo(() => {
        const map = new Map<string, string>();

        for (const transaction of transactions) {
            if (transaction.category) {
                map.set(
                    transaction.category,
                    transaction.category_label ?? transaction.category,
                );
            }
        }

        return [...map.entries()]
            .map(([value, label]) => ({ value, label }))
            .sort((a, b) => a.label.localeCompare(b.label));
    }, [transactions]);

    const hasUncategorized = transactions.some((t) => t.category === null);

    const filtered = useMemo(() => {
        const query = search.trim().toLowerCase();

        return transactions.filter((transaction) => {
            const matchesText =
                query === '' ||
                (transaction.merchant ?? '').toLowerCase().includes(query) ||
                transaction.description.toLowerCase().includes(query) ||
                (transaction.category_label ?? '')
                    .toLowerCase()
                    .includes(query);

            const matchesCategory =
                categoryFilter === 'all' ||
                (categoryFilter === UNCATEGORIZED
                    ? transaction.category === null
                    : transaction.category === categoryFilter);

            return matchesText && matchesCategory;
        });
    }, [transactions, search, categoryFilter]);

    // Totals are computed from the filtered movements, so the cards update live.
    const summary = useMemo(() => {
        let deposits = 0;
        let withdrawals = 0;

        for (const transaction of filtered) {
            const amount = Number(transaction.amount);

            if (transaction.direction === 'credit') {
                deposits += amount;
            } else {
                withdrawals += amount;
            }
        }

        return { deposits, withdrawals, net: deposits - withdrawals };
    }, [filtered]);

    const isFiltering = search.trim() !== '' || categoryFilter !== 'all';
    const filterHint = isFiltering ? 'filtrado' : undefined;

    return (
        <>
            <Head title={statement.original_filename} />

            <div className="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={statement.original_filename}
                    description={
                        statement.period_start && statement.period_end
                            ? `${formatDate(statement.period_start)} — ${formatDate(statement.period_end)}`
                            : `${account.name} · ${account.bank}`
                    }
                    action={
                        <StatementStatusBadge
                            status={statement.status}
                            label={statement.status_label}
                        />
                    }
                />

                {statement.status === 'failed' && (
                    <div className="flex flex-col gap-3 rounded-xl bg-destructive/10 p-4 text-destructive sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-start gap-3">
                            <TriangleAlert className="mt-0.5 size-5 shrink-0" />
                            <div className="text-sm">
                                <p className="font-medium">
                                    No se pudo procesar el estado de cuenta.
                                </p>
                                {statement.failure_reason && (
                                    <p className="text-destructive/80">
                                        {statement.failure_reason}
                                    </p>
                                )}
                            </div>
                        </div>
                        <div className="shrink-0">
                            <StatementReuploadDialog
                                statementId={statement.uuid}
                            />
                        </div>
                    </div>
                )}

                {statement.status === 'needs_review' && (
                    <div className="flex flex-col gap-3 rounded-xl bg-amber-500/10 p-4 text-amber-600 sm:flex-row sm:items-center sm:justify-between dark:text-amber-400">
                        <div className="flex items-start gap-3">
                            <AlertTriangle className="mt-0.5 size-5 shrink-0" />
                            <div className="text-sm">
                                <p className="font-medium">
                                    Los saldos no cuadraron.
                                </p>
                                <p className="opacity-80">
                                    Hay una diferencia de{' '}
                                    {formatCurrency(
                                        statement.reconciliation_diff,
                                        currency,
                                    )}
                                    . Revisa los movimientos o vuelve a subir el
                                    PDF.
                                </p>
                            </div>
                        </div>
                        <div className="shrink-0">
                            <StatementReuploadDialog
                                statementId={statement.uuid}
                            />
                        </div>
                    </div>
                )}

                {isInProgress && (
                    <div className="rounded-xl bg-muted/50 p-4 text-sm text-muted-foreground">
                        Estamos extrayendo los movimientos de tu estado de
                        cuenta. Esta página se actualizará automáticamente.
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-3">
                    {isInProgress ? (
                        Array.from({ length: 3 }).map((_, index) => (
                            <Card key={index}>
                                <CardContent className="space-y-2 py-4">
                                    <Skeleton className="h-3 w-20" />
                                    <Skeleton className="h-7 w-28" />
                                </CardContent>
                            </Card>
                        ))
                    ) : (
                        <>
                            <Kpi
                                label="Depósitos"
                                accent="brand"
                                hint={filterHint}
                                value={formatCurrency(
                                    summary.deposits,
                                    currency,
                                )}
                            />
                            <Kpi
                                label="Retiros"
                                accent="negative"
                                hint={filterHint}
                                value={formatCurrency(
                                    summary.withdrawals,
                                    currency,
                                )}
                            />
                            <Kpi
                                label="Neto"
                                accent={summary.net >= 0 ? 'brand' : 'negative'}
                                hint={filterHint}
                                value={formatCurrency(summary.net, currency)}
                            />
                        </>
                    )}
                </div>

                {isInProgress ? (
                    <Card>
                        <CardContent className="space-y-3 py-4">
                            {Array.from({ length: 6 }).map((_, index) => (
                                <div
                                    key={index}
                                    className="flex items-center gap-4"
                                >
                                    <Skeleton className="h-4 w-20" />
                                    <Skeleton className="h-4 flex-1" />
                                    <Skeleton className="h-4 w-24" />
                                </div>
                            ))}
                        </CardContent>
                    </Card>
                ) : (
                    <Tabs defaultValue="movimientos" className="gap-4">
                        <TabsList>
                            <TabsTrigger value="movimientos">
                                Movimientos
                            </TabsTrigger>
                            <TabsTrigger value="categorias">
                                Categorías
                            </TabsTrigger>
                            <TabsTrigger value="anomalias">
                                Anomalías
                                {anomalies.length > 0 && (
                                    <span className="ml-1 rounded-full bg-destructive/10 px-1.5 text-xs text-destructive tabular-nums">
                                        {anomalies.length}
                                    </span>
                                )}
                            </TabsTrigger>
                        </TabsList>

                        <TabsContent value="movimientos">
                            <MovementsTab
                                filtered={filtered}
                                totalCount={transactions.length}
                                search={search}
                                onSearchChange={setSearch}
                                categoryFilter={categoryFilter}
                                onCategoryFilterChange={setCategoryFilter}
                                presentCategories={presentCategories}
                                hasUncategorized={hasUncategorized}
                                categoryOptions={categoryOptions}
                                customCategories={customCategories}
                                currency={currency}
                            />
                        </TabsContent>

                        <TabsContent value="categorias">
                            <CategoriesTab
                                transactions={transactions}
                                currency={currency}
                            />
                        </TabsContent>

                        <TabsContent value="anomalias">
                            <AnomaliesTab
                                anomalies={anomalies}
                                currency={currency}
                            />
                        </TabsContent>
                    </Tabs>
                )}
            </div>
        </>
    );
}

StatementShow.layout = {
    breadcrumbs: [{ title: 'Cuentas', href: accountsIndex() }],
};

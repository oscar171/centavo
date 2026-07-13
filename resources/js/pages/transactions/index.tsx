import { Head, Link, router } from '@inertiajs/react';
import { ArrowDownLeft, ArrowUpRight, Receipt, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import StatementController from '@/actions/App/Http/Controllers/StatementController';
import CategoryBadge from '@/components/category-badge';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatCurrency, formatDate } from '@/lib/format';
import { index as transactionsIndex } from '@/routes/transactions';

type AccountOption = { uuid: string; name: string };

type CategoryOption = { value: string; label: string };

type TransactionRow = {
    uuid: string;
    date: string;
    description: string;
    merchant: string | null;
    amount: string;
    direction: string;
    category: string | null;
    category_label: string | null;
    account_name: string;
    currency: string;
    statement_uuid: string;
};

type Paginated = {
    data: TransactionRow[];
    prev_page_url: string | null;
    next_page_url: string | null;
    from: number | null;
    to: number | null;
    total: number;
};

type Filters = {
    search: string;
    direction: string;
    category: string;
    min: string;
    max: string;
    from: string;
    to: string;
};

type Summary = {
    income: number;
    expense: number;
    net: number;
    count: number;
};

type PageProps = {
    transactions: Paginated;
    accounts: AccountOption[];
    selectedAccount: string | null;
    currency: string;
    filters: Filters;
    presentCategories: CategoryOption[];
    hasUncategorized: boolean;
    summary: Summary;
};

const ALL_ACCOUNTS = 'all';

const UNCATEGORIZED = 'uncategorized';

function Kpi({
    label,
    value,
    accent,
}: {
    label: string;
    value: string;
    accent?: 'brand' | 'negative';
}) {
    const color =
        accent === 'brand'
            ? 'text-brand'
            : accent === 'negative'
              ? 'text-negative'
              : '';

    return (
        <Card>
            <CardContent className="space-y-1 py-4">
                <p className="text-xs tracking-wide text-muted-foreground uppercase">
                    {label}
                </p>
                <p className={`text-2xl font-semibold tabular-nums ${color}`}>
                    {value}
                </p>
            </CardContent>
        </Card>
    );
}

export default function TransactionsIndex({
    transactions,
    accounts,
    selectedAccount,
    currency,
    filters,
    presentCategories,
    hasUncategorized,
    summary,
}: PageProps) {
    const [search, setSearch] = useState(filters.search);
    const [account, setAccount] = useState(selectedAccount ?? ALL_ACCOUNTS);
    const [direction, setDirection] = useState(filters.direction || 'all');
    const [category, setCategory] = useState(filters.category || 'all');
    const [min, setMin] = useState(filters.min);
    const [max, setMax] = useState(filters.max);
    const [from, setFrom] = useState(filters.from);
    const [to, setTo] = useState(filters.to);

    // Skip the first run so simply landing on the page doesn't fire a redundant
    // request; afterwards, debounce filter changes into a single server visit.
    const isFirst = useRef(true);

    useEffect(() => {
        if (isFirst.current) {
            isFirst.current = false;

            return;
        }

        const handler = setTimeout(() => {
            const params: Record<string, string> = {};

            if (account !== ALL_ACCOUNTS) {
                params.account = account;
            }

            if (search.trim() !== '') {
                params.q = search.trim();
            }

            if (direction !== 'all') {
                params.direction = direction;
            }

            if (category !== 'all') {
                params.category = category;
            }

            if (min.trim() !== '') {
                params.min = min.trim();
            }

            if (max.trim() !== '') {
                params.max = max.trim();
            }

            if (from !== '') {
                params.from = from;
            }

            if (to !== '') {
                params.to = to;
            }

            router.get(transactionsIndex().url, params, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 300);

        return () => clearTimeout(handler);
    }, [search, account, direction, category, min, max, from, to]);

    const hasFilters =
        search.trim() !== '' ||
        account !== ALL_ACCOUNTS ||
        direction !== 'all' ||
        category !== 'all' ||
        min.trim() !== '' ||
        max.trim() !== '' ||
        from !== '' ||
        to !== '';

    const clearFilters = () => {
        setSearch('');
        setAccount(ALL_ACCOUNTS);
        setDirection('all');
        setCategory('all');
        setMin('');
        setMax('');
        setFrom('');
        setTo('');
    };

    const noData = transactions.total === 0 && !hasFilters;

    return (
        <>
            <Head title="Movimientos" />

            <div className="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Movimientos"
                    description="Todos los movimientos de tus cuentas."
                />

                {noData ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border p-12 text-center">
                        <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                            <Receipt className="size-6 text-muted-foreground" />
                        </div>
                        <div>
                            <p className="font-medium">Sin movimientos</p>
                            <p className="text-sm text-muted-foreground">
                                Sube un estado de cuenta para ver tus
                                movimientos aquí.
                            </p>
                        </div>
                    </div>
                ) : (
                    <>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Kpi
                                label="Ingresos"
                                value={formatCurrency(summary.income, currency)}
                                accent="brand"
                            />
                            <Kpi
                                label="Gastos"
                                value={formatCurrency(
                                    summary.expense,
                                    currency,
                                )}
                                accent="negative"
                            />
                            <Kpi
                                label="Neto"
                                value={formatCurrency(summary.net, currency)}
                                accent={summary.net >= 0 ? 'brand' : 'negative'}
                            />
                        </div>

                        <Card>
                            <CardContent className="space-y-3 py-4">
                                <div className="flex flex-col gap-3 sm:flex-row sm:items-center">
                                    <div className="relative w-full sm:max-w-xs">
                                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            value={search}
                                            onChange={(event) =>
                                                setSearch(event.target.value)
                                            }
                                            placeholder="Buscar descripción, comercio o categoría"
                                            className="pl-9"
                                        />
                                    </div>
                                    {accounts.length > 1 && (
                                        <Select
                                            value={account}
                                            onValueChange={setAccount}
                                        >
                                            <SelectTrigger className="w-full sm:w-48">
                                                <SelectValue />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    value={ALL_ACCOUNTS}
                                                >
                                                    Todas las cuentas
                                                </SelectItem>
                                                {accounts.map((option) => (
                                                    <SelectItem
                                                        key={option.uuid}
                                                        value={option.uuid}
                                                    >
                                                        {option.name}
                                                    </SelectItem>
                                                ))}
                                            </SelectContent>
                                        </Select>
                                    )}
                                    <Select
                                        value={direction}
                                        onValueChange={setDirection}
                                    >
                                        <SelectTrigger className="w-full sm:w-40">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">
                                                Todos los tipos
                                            </SelectItem>
                                            <SelectItem value="credit">
                                                Ingresos
                                            </SelectItem>
                                            <SelectItem value="debit">
                                                Egresos
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                    <Select
                                        value={category}
                                        onValueChange={setCategory}
                                    >
                                        <SelectTrigger className="w-full sm:w-52">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="all">
                                                Todas las categorías
                                            </SelectItem>
                                            {presentCategories.map((option) => (
                                                <SelectItem
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </SelectItem>
                                            ))}
                                            {hasUncategorized && (
                                                <SelectItem
                                                    value={UNCATEGORIZED}
                                                >
                                                    Sin categoría
                                                </SelectItem>
                                            )}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="text-xs text-muted-foreground">
                                        Monto
                                    </span>
                                    <Input
                                        type="number"
                                        inputMode="decimal"
                                        min="0"
                                        step="0.01"
                                        value={min}
                                        onChange={(event) =>
                                            setMin(event.target.value)
                                        }
                                        placeholder="Mín"
                                        className="w-24 tabular-nums"
                                    />
                                    <span className="text-muted-foreground">
                                        –
                                    </span>
                                    <Input
                                        type="number"
                                        inputMode="decimal"
                                        min="0"
                                        step="0.01"
                                        value={max}
                                        onChange={(event) =>
                                            setMax(event.target.value)
                                        }
                                        placeholder="Máx"
                                        className="w-24 tabular-nums"
                                    />
                                    <span className="ml-2 text-xs text-muted-foreground">
                                        Fecha
                                    </span>
                                    <Input
                                        type="date"
                                        value={from}
                                        onChange={(event) =>
                                            setFrom(event.target.value)
                                        }
                                        className="w-40 tabular-nums"
                                    />
                                    <span className="text-muted-foreground">
                                        –
                                    </span>
                                    <Input
                                        type="date"
                                        value={to}
                                        onChange={(event) =>
                                            setTo(event.target.value)
                                        }
                                        className="w-40 tabular-nums"
                                    />
                                    {hasFilters && (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={clearFilters}
                                            className="ml-auto"
                                        >
                                            <X className="size-4" />
                                            Limpiar
                                        </Button>
                                    )}
                                </div>
                            </CardContent>
                        </Card>

                        {transactions.data.length === 0 ? (
                            <div className="rounded-xl border border-dashed border-border p-12 text-center text-sm text-muted-foreground">
                                No hay movimientos que coincidan con los
                                filtros.
                            </div>
                        ) : (
                            <>
                                <div className="overflow-x-auto rounded-xl border border-border">
                                    <Table>
                                        <TableHeader>
                                            <TableRow>
                                                <TableHead className="w-10">
                                                    <span className="sr-only">
                                                        Tipo
                                                    </span>
                                                </TableHead>
                                                <TableHead>Fecha</TableHead>
                                                <TableHead>
                                                    Descripción
                                                </TableHead>
                                                <TableHead>Categoría</TableHead>
                                                <TableHead>Cuenta</TableHead>
                                                <TableHead className="text-right">
                                                    Monto
                                                </TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {transactions.data.map(
                                                (transaction) => (
                                                    <TableRow
                                                        key={transaction.uuid}
                                                        className="cursor-pointer"
                                                        onClick={() =>
                                                            router.visit(
                                                                StatementController.show(
                                                                    transaction.statement_uuid,
                                                                ).url,
                                                            )
                                                        }
                                                    >
                                                        <TableCell>
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
                                                        <TableCell className="whitespace-nowrap text-muted-foreground tabular-nums">
                                                            {formatDate(
                                                                transaction.date,
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="max-w-md">
                                                            <p className="font-medium">
                                                                {transaction.merchant ??
                                                                    transaction.description}
                                                            </p>
                                                            {transaction.merchant && (
                                                                <p className="truncate text-xs text-muted-foreground">
                                                                    {
                                                                        transaction.description
                                                                    }
                                                                </p>
                                                            )}
                                                        </TableCell>
                                                        <TableCell>
                                                            <CategoryBadge
                                                                value={
                                                                    transaction.category
                                                                }
                                                                label={
                                                                    transaction.category_label
                                                                }
                                                            />
                                                        </TableCell>
                                                        <TableCell className="whitespace-nowrap text-muted-foreground">
                                                            {
                                                                transaction.account_name
                                                            }
                                                        </TableCell>
                                                        <TableCell className="text-right whitespace-nowrap tabular-nums">
                                                            <span
                                                                className={
                                                                    transaction.direction ===
                                                                    'credit'
                                                                        ? 'text-brand'
                                                                        : 'text-negative'
                                                                }
                                                            >
                                                                {transaction.direction ===
                                                                'credit'
                                                                    ? '+'
                                                                    : '−'}
                                                                {formatCurrency(
                                                                    transaction.amount,
                                                                    transaction.currency,
                                                                )}
                                                            </span>
                                                        </TableCell>
                                                    </TableRow>
                                                ),
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>

                                <div className="flex items-center justify-between">
                                    <p className="text-sm text-muted-foreground tabular-nums">
                                        {transactions.from}–{transactions.to} de{' '}
                                        {transactions.total}
                                    </p>
                                    <div className="flex gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={
                                                !transactions.prev_page_url
                                            }
                                            asChild={
                                                !!transactions.prev_page_url
                                            }
                                        >
                                            {transactions.prev_page_url ? (
                                                <Link
                                                    href={
                                                        transactions.prev_page_url
                                                    }
                                                    preserveScroll
                                                >
                                                    Anterior
                                                </Link>
                                            ) : (
                                                <span>Anterior</span>
                                            )}
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            disabled={
                                                !transactions.next_page_url
                                            }
                                            asChild={
                                                !!transactions.next_page_url
                                            }
                                        >
                                            {transactions.next_page_url ? (
                                                <Link
                                                    href={
                                                        transactions.next_page_url
                                                    }
                                                    preserveScroll
                                                >
                                                    Siguiente
                                                </Link>
                                            ) : (
                                                <span>Siguiente</span>
                                            )}
                                        </Button>
                                    </div>
                                </div>
                            </>
                        )}
                    </>
                )}
            </div>
        </>
    );
}

TransactionsIndex.layout = {
    breadcrumbs: [{ title: 'Movimientos', href: transactionsIndex() }],
};

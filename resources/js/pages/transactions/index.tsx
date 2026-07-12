import { Head, Link, router } from '@inertiajs/react';
import { Receipt } from 'lucide-react';
import StatementController from '@/actions/App/Http/Controllers/StatementController';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
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

type TransactionRow = {
    uuid: string;
    date: string;
    description: string;
    merchant: string | null;
    amount: string;
    direction: string;
    running_balance: string | null;
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

type PageProps = {
    transactions: Paginated;
    accounts: AccountOption[];
    selectedAccount: string | null;
};

const ALL_ACCOUNTS = 'all';

export default function TransactionsIndex({
    transactions,
    accounts,
    selectedAccount,
}: PageProps) {
    const onAccountChange = (value: string) => {
        router.get(
            transactionsIndex().url,
            value === ALL_ACCOUNTS ? {} : { account: value },
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Movimientos" />

            <div className="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Movimientos"
                    description="Todos los movimientos de tus cuentas."
                    action={
                        accounts.length > 1 ? (
                            <Select
                                value={selectedAccount ?? ALL_ACCOUNTS}
                                onValueChange={onAccountChange}
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

                {transactions.data.length === 0 ? (
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
                        <div className="rounded-xl border border-border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Fecha</TableHead>
                                        <TableHead>Descripción</TableHead>
                                        <TableHead>Cuenta</TableHead>
                                        <TableHead className="text-right">
                                            Monto
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {transactions.data.map((transaction) => (
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
                                            <TableCell className="text-muted-foreground tabular-nums">
                                                {formatDate(transaction.date)}
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
                                            <TableCell className="text-muted-foreground">
                                                {transaction.account_name}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">
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
                                    ))}
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
                                    disabled={!transactions.prev_page_url}
                                    asChild={!!transactions.prev_page_url}
                                >
                                    {transactions.prev_page_url ? (
                                        <Link
                                            href={transactions.prev_page_url}
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
                                    disabled={!transactions.next_page_url}
                                    asChild={!!transactions.next_page_url}
                                >
                                    {transactions.next_page_url ? (
                                        <Link
                                            href={transactions.next_page_url}
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
            </div>
        </>
    );
}

TransactionsIndex.layout = {
    breadcrumbs: [{ title: 'Movimientos', href: transactionsIndex() }],
};

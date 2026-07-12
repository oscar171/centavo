import { Head, Link } from '@inertiajs/react';
import { CreditCard, FileText } from 'lucide-react';
import StatementController from '@/actions/App/Http/Controllers/StatementController';
import PageHeader from '@/components/page-header';
import StatementStatusBadge from '@/components/statement-status-badge';
import StatementUploadDialog from '@/components/statement-upload-dialog';
import { Card, CardContent } from '@/components/ui/card';
import { index as accountsIndex } from '@/routes/accounts';

type Account = {
    uuid: string;
    name: string;
    bank: string;
    account_type: string | null;
    account_type_label: string | null;
    last_four: string | null;
    currency: string;
};

type StatementListItem = {
    uuid: string;
    original_filename: string;
    status: string;
    status_label: string;
    period_start: string | null;
    period_end: string | null;
    created_at: string | null;
};

type PageProps = {
    account: Account;
    statements: StatementListItem[];
};

export default function AccountShow({ account, statements }: PageProps) {
    const meta = [
        account.account_type_label,
        account.last_four ? `•••• ${account.last_four}` : null,
        account.currency,
    ]
        .filter(Boolean)
        .join(' · ');

    return (
        <>
            <Head title={account.name} />

            <div className="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title={account.name}
                    description={meta}
                    kicker={
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <CreditCard className="size-4" />
                            <span className="text-xs tracking-wide uppercase">
                                {account.bank}
                            </span>
                        </div>
                    }
                    action={<StatementUploadDialog accountId={account.uuid} />}
                />

                <div className="space-y-3">
                    <h2 className="text-lg font-medium">Estados de cuenta</h2>

                    {statements.length === 0 ? (
                        <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border p-12 text-center">
                            <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                                <FileText className="size-6 text-muted-foreground" />
                            </div>
                            <div>
                                <p className="font-medium">
                                    Sin estados de cuenta
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Sube el PDF de tu estado de cuenta para
                                    extraer los movimientos.
                                </p>
                            </div>
                            <StatementUploadDialog accountId={account.uuid} />
                        </div>
                    ) : (
                        <div className="space-y-2">
                            {statements.map((statement) => (
                                <Link
                                    key={statement.uuid}
                                    href={StatementController.show(
                                        statement.uuid,
                                    )}
                                    className="block rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                >
                                    <Card className="transition-colors hover:border-foreground/20">
                                        <CardContent className="flex items-center justify-between gap-4 py-4">
                                            <div className="flex items-center gap-3">
                                                <FileText className="size-5 text-muted-foreground" />
                                                <div>
                                                    <p className="font-medium">
                                                        {
                                                            statement.original_filename
                                                        }
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {statement.period_start &&
                                                        statement.period_end
                                                            ? `${statement.period_start} — ${statement.period_end}`
                                                            : `Subido ${statement.created_at ?? ''}`}
                                                    </p>
                                                </div>
                                            </div>
                                            <StatementStatusBadge
                                                status={statement.status}
                                                label={statement.status_label}
                                            />
                                        </CardContent>
                                    </Card>
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

AccountShow.layout = {
    breadcrumbs: [{ title: 'Cuentas', href: accountsIndex() }],
};

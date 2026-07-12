import { Head, Link } from '@inertiajs/react';
import { CreditCard, Plus, Wallet } from 'lucide-react';
import AccountController from '@/actions/App/Http/Controllers/AccountController';
import CreateAccountDialog from '@/components/create-account-dialog';
import PageHeader from '@/components/page-header';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { index as accountsIndex } from '@/routes/accounts';

type AccountTypeOption = { value: string; label: string };

type AccountListItem = {
    uuid: string;
    name: string;
    bank: string;
    account_type: string | null;
    account_type_label: string | null;
    last_four: string | null;
    currency: string;
    statements_count: number;
};

type PageProps = {
    accounts: AccountListItem[];
    banks: string[];
    accountTypes: AccountTypeOption[];
};

export default function AccountsIndex({
    accounts,
    banks,
    accountTypes,
}: PageProps) {
    return (
        <>
            <Head title="Cuentas" />

            <div className="mx-auto flex h-full w-full max-w-6xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Cuentas"
                    description="Tus cuentas bancarias. Sube estados de cuenta a cada una."
                    action={
                        <CreateAccountDialog
                            banks={banks}
                            accountTypes={accountTypes}
                            trigger={
                                <Button>
                                    <Plus className="size-4" />
                                    Nueva cuenta
                                </Button>
                            }
                        />
                    }
                />

                {accounts.length === 0 ? (
                    <div className="flex flex-1 flex-col items-center justify-center gap-3 rounded-xl border border-dashed p-12 text-center">
                        <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                            <Wallet className="size-6 text-muted-foreground" />
                        </div>
                        <div>
                            <p className="font-medium">Aún no tienes cuentas</p>
                            <p className="text-sm text-muted-foreground">
                                Crea tu primera cuenta para empezar a subir
                                estados de cuenta.
                            </p>
                        </div>
                        <CreateAccountDialog
                            banks={banks}
                            accountTypes={accountTypes}
                            trigger={
                                <Button>
                                    <Plus className="size-4" />
                                    Nueva cuenta
                                </Button>
                            }
                        />
                    </div>
                ) : (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {accounts.map((account) => (
                            <Link
                                key={account.uuid}
                                href={AccountController.show(account.uuid)}
                                className="rounded-xl outline-none focus-visible:ring-2 focus-visible:ring-ring"
                            >
                                <Card className="h-full transition-colors hover:border-foreground/20">
                                    <CardHeader>
                                        <div className="flex items-center gap-2 text-muted-foreground">
                                            <CreditCard className="size-4" />
                                            <span className="text-xs tracking-wide uppercase">
                                                {account.bank}
                                            </span>
                                        </div>
                                        <CardTitle>{account.name}</CardTitle>
                                        <CardDescription>
                                            {[
                                                account.account_type_label,
                                                account.last_four
                                                    ? `•••• ${account.last_four}`
                                                    : null,
                                                account.currency,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </CardDescription>
                                    </CardHeader>
                                    <CardContent className="text-sm text-muted-foreground">
                                        {account.statements_count === 1
                                            ? '1 estado de cuenta'
                                            : `${account.statements_count} estados de cuenta`}
                                    </CardContent>
                                </Card>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </>
    );
}

AccountsIndex.layout = {
    breadcrumbs: [{ title: 'Cuentas', href: accountsIndex() }],
};

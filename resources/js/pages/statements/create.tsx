import { Form, Head, Link } from '@inertiajs/react';
import { FileText, Plus, Upload, Wallet } from 'lucide-react';
import { useRef, useState } from 'react';
import StatementController from '@/actions/App/Http/Controllers/StatementController';
import CreateAccountDialog from '@/components/create-account-dialog';
import InputError from '@/components/input-error';
import PageHeader from '@/components/page-header';
import StatementStatusBadge from '@/components/statement-status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';
import { create as statementsCreate } from '@/routes/statements';

type AccountOption = { uuid: string; name: string; bank: string };

type AccountTypeOption = { value: string; label: string };

type StatementListItem = {
    uuid: string;
    original_filename: string;
    status: string;
    status_label: string;
    account_name: string;
    bank: string;
    period_start: string | null;
    period_end: string | null;
};

type PageProps = {
    accounts: AccountOption[];
    statements: StatementListItem[];
    banks: string[];
    accountTypes: AccountTypeOption[];
};

export default function StatementsCreate({
    accounts,
    statements,
    banks,
    accountTypes,
}: PageProps) {
    const [selectedAccount, setSelectedAccount] = useState('');
    const [fileName, setFileName] = useState<string | null>(null);
    const [dragging, setDragging] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    // Fall back to the first account (e.g. right after creating the first one)
    // until the user explicitly picks another.
    const accountId = selectedAccount || (accounts[0]?.uuid ?? '');

    const handleDrop = (event: React.DragEvent<HTMLLabelElement>) => {
        event.preventDefault();
        setDragging(false);

        const files = event.dataTransfer.files;

        if (files.length > 0 && inputRef.current) {
            inputRef.current.files = files;
            setFileName(files[0].name);
        }
    };

    if (accounts.length === 0) {
        return (
            <>
                <Head title="Estados de cuenta" />
                <div className="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                    <PageHeader
                        title="Estados de cuenta"
                        description="Sube el PDF de tu estado de cuenta."
                    />
                    <div className="flex flex-1 flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-border p-12 text-center">
                        <div className="flex size-12 items-center justify-center rounded-full bg-muted">
                            <Wallet className="size-6 text-muted-foreground" />
                        </div>
                        <div>
                            <p className="font-medium">
                                Primero crea una cuenta
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Los estados de cuenta se asocian a una cuenta.
                            </p>
                        </div>
                        <CreateAccountDialog
                            banks={banks}
                            accountTypes={accountTypes}
                            trigger={
                                <Button>
                                    <Plus className="size-4" />
                                    Agregar cuenta
                                </Button>
                            }
                        />
                    </div>
                </div>
            </>
        );
    }

    return (
        <>
            <Head title="Estados de cuenta" />

            <div className="mx-auto flex h-full w-full max-w-4xl flex-1 flex-col gap-6 p-4 md:p-6">
                <PageHeader
                    title="Estados de cuenta"
                    description="Sube el PDF de tu banco y consulta los que ya procesamos."
                />

                <div className="space-y-3">
                    <h2 className="text-lg font-medium">
                        Subir un estado de cuenta
                    </h2>
                    <Card>
                        <CardContent className="py-6">
                            <Form
                                {...StatementController.store.form(accountId)}
                                className="space-y-5"
                            >
                                {({ processing, errors, progress }) => (
                                    <>
                                        <div className="grid gap-2">
                                            <Label htmlFor="account">
                                                Cuenta
                                            </Label>
                                            <div className="flex items-center gap-2">
                                                <Select
                                                    value={accountId}
                                                    onValueChange={
                                                        setSelectedAccount
                                                    }
                                                >
                                                    <SelectTrigger
                                                        id="account"
                                                        className="w-full"
                                                    >
                                                        <SelectValue placeholder="Selecciona una cuenta" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {accounts.map(
                                                            (account) => (
                                                                <SelectItem
                                                                    key={
                                                                        account.uuid
                                                                    }
                                                                    value={
                                                                        account.uuid
                                                                    }
                                                                >
                                                                    <span className="flex items-center gap-2">
                                                                        {
                                                                            account.name
                                                                        }
                                                                        <Badge variant="secondary">
                                                                            {
                                                                                account.bank
                                                                            }
                                                                        </Badge>
                                                                    </span>
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                                <CreateAccountDialog
                                                    banks={banks}
                                                    accountTypes={accountTypes}
                                                    trigger={
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            className="shrink-0"
                                                        >
                                                            <Plus className="size-4" />
                                                            Agregar cuenta
                                                        </Button>
                                                    }
                                                />
                                            </div>
                                        </div>

                                        <label
                                            onDragOver={(event) => {
                                                event.preventDefault();
                                                setDragging(true);
                                            }}
                                            onDragLeave={() =>
                                                setDragging(false)
                                            }
                                            onDrop={handleDrop}
                                            className={cn(
                                                'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-border p-8 text-center transition-colors hover:border-brand/50',
                                                dragging &&
                                                    'border-brand/60 bg-brand/5',
                                            )}
                                        >
                                            {fileName ? (
                                                <>
                                                    <FileText className="size-6 text-brand" />
                                                    <span className="text-sm font-medium">
                                                        {fileName}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        Haz clic para cambiar el
                                                        archivo
                                                    </span>
                                                </>
                                            ) : (
                                                <>
                                                    <Upload className="size-6 text-muted-foreground" />
                                                    <span className="text-sm font-medium">
                                                        Arrastra el PDF aquí o
                                                        haz clic para
                                                        seleccionar
                                                    </span>
                                                    <span className="text-xs text-muted-foreground">
                                                        PDF · máximo 20 MB
                                                    </span>
                                                </>
                                            )}
                                            <input
                                                ref={inputRef}
                                                type="file"
                                                name="file"
                                                accept="application/pdf"
                                                className="sr-only"
                                                onChange={(event) =>
                                                    setFileName(
                                                        event.target.files?.[0]
                                                            ?.name ?? null,
                                                    )
                                                }
                                            />
                                        </label>

                                        <InputError message={errors.file} />

                                        {progress && (
                                            <div className="h-1.5 w-full overflow-hidden rounded-full bg-muted">
                                                <div
                                                    className="h-full bg-brand transition-all"
                                                    style={{
                                                        width: `${progress.percentage}%`,
                                                    }}
                                                />
                                            </div>
                                        )}

                                        <div className="flex justify-end">
                                            <Button
                                                type="submit"
                                                disabled={
                                                    processing || !fileName
                                                }
                                            >
                                                {processing
                                                    ? 'Subiendo…'
                                                    : 'Subir y procesar'}
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        </CardContent>
                    </Card>
                </div>

                <div className="space-y-3">
                    <h2 className="text-lg font-medium">Subidos</h2>

                    {statements.length === 0 ? (
                        <div className="rounded-xl border border-dashed border-border p-8 text-center text-sm text-muted-foreground">
                            Aún no has subido estados de cuenta.
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

StatementsCreate.layout = {
    breadcrumbs: [{ title: 'Estados de cuenta', href: statementsCreate() }],
};

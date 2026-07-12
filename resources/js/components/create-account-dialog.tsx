import { Form } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';
import AccountController from '@/actions/App/Http/Controllers/AccountController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

type AccountTypeOption = { value: string; label: string };

const OTHER_BANK = '__other__';

/**
 * Reusable "create account" modal. On success the server redirects back to the
 * current page, so whichever screen it is opened from (accounts list or the
 * upload page) refreshes its accounts in place.
 */
export default function CreateAccountDialog({
    banks,
    accountTypes,
    trigger,
}: {
    banks: string[];
    accountTypes: AccountTypeOption[];
    trigger: ReactNode;
}) {
    const [open, setOpen] = useState(false);
    const [bankChoice, setBankChoice] = useState('');
    const [bankOther, setBankOther] = useState('');
    const [accountType, setAccountType] = useState('');

    const effectiveBank = bankChoice === OTHER_BANK ? bankOther : bankChoice;

    const resetForm = () => {
        setBankChoice('');
        setBankOther('');
        setAccountType('');
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);

                if (!value) {
                    resetForm();
                }
            }}
        >
            <DialogTrigger asChild>{trigger}</DialogTrigger>

            <DialogContent>
                <Form
                    {...AccountController.store.form()}
                    options={{ preserveScroll: true, preserveState: true }}
                    onSuccess={() => {
                        setOpen(false);
                        resetForm();
                    }}
                    className="space-y-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Nueva cuenta</DialogTitle>
                                <DialogDescription>
                                    Registra una cuenta para asociarle estados
                                    de cuenta.
                                </DialogDescription>
                            </DialogHeader>

                            <div className="grid gap-2">
                                <Label htmlFor="name">Nombre</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    placeholder="Cuenta principal"
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="bank">Banco</Label>
                                <input
                                    type="hidden"
                                    name="bank"
                                    value={effectiveBank}
                                />
                                <Select
                                    value={bankChoice}
                                    onValueChange={setBankChoice}
                                >
                                    <SelectTrigger id="bank" className="w-full">
                                        <SelectValue placeholder="Selecciona un banco" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {banks.map((bank) => (
                                            <SelectItem key={bank} value={bank}>
                                                {bank}
                                            </SelectItem>
                                        ))}
                                        <SelectItem value={OTHER_BANK}>
                                            Otro…
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {bankChoice === OTHER_BANK && (
                                    <Input
                                        aria-label="Nombre del banco"
                                        placeholder="Nombre del banco"
                                        value={bankOther}
                                        onChange={(event) =>
                                            setBankOther(event.target.value)
                                        }
                                    />
                                )}
                                <InputError message={errors.bank} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="account_type">Tipo</Label>
                                    <input
                                        type="hidden"
                                        name="account_type"
                                        value={accountType}
                                    />
                                    <Select
                                        value={accountType}
                                        onValueChange={setAccountType}
                                    >
                                        <SelectTrigger
                                            id="account_type"
                                            className="w-full"
                                        >
                                            <SelectValue placeholder="Opcional" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {accountTypes.map((type) => (
                                                <SelectItem
                                                    key={type.value}
                                                    value={type.value}
                                                >
                                                    {type.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    <InputError message={errors.account_type} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="last_four">Últimos 4</Label>
                                    <Input
                                        id="last_four"
                                        name="last_four"
                                        inputMode="numeric"
                                        maxLength={4}
                                        placeholder="1234"
                                    />
                                    <InputError message={errors.last_four} />
                                </div>
                            </div>

                            <input type="hidden" name="currency" value="USD" />

                            <DialogFooter>
                                <Button type="submit" disabled={processing}>
                                    Crear cuenta
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

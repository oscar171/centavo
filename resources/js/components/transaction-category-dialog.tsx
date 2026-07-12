import { useForm } from '@inertiajs/react';
import { Check, ChevronsUpDown, Plus } from 'lucide-react';
import { useState } from 'react';
import TransactionController from '@/actions/App/Http/Controllers/TransactionController';
import { categoryColor } from '@/components/category-badge';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from '@/components/ui/command';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';

type CategoryOption = { value: string; label: string };

type TransactionSummary = {
    uuid: string;
    merchant: string | null;
    description: string;
    category: string | null;
};

function Dot({ value }: { value: string }) {
    return (
        <span
            className="size-2.5 shrink-0 rounded-full"
            style={{ backgroundColor: categoryColor(value) }}
        />
    );
}

export default function TransactionCategoryDialog({
    transaction,
    categoryOptions,
    customCategories = [],
}: {
    transaction: TransactionSummary;
    categoryOptions: CategoryOption[];
    customCategories?: string[];
}) {
    const [open, setOpen] = useState(false);
    const [pickerOpen, setPickerOpen] = useState(false);
    const [query, setQuery] = useState('');

    const form = useForm<{ category: string; apply_to_all: boolean }>({
        category: transaction.category ?? '',
        apply_to_all: false,
    });

    const selected = form.data.category;
    const selectedLabel =
        categoryOptions.find((option) => option.value === selected)?.label ??
        selected;

    const trimmed = query.trim();
    const existsExactly =
        categoryOptions.some(
            (option) => option.label.toLowerCase() === trimmed.toLowerCase(),
        ) ||
        customCategories.some(
            (name) => name.toLowerCase() === trimmed.toLowerCase(),
        );
    const canCreate = trimmed !== '' && !existsExactly;

    const choose = (value: string) => {
        form.setData('category', value);
        setQuery('');
        setPickerOpen(false);
    };

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        const action = TransactionController.updateCategory(transaction.uuid);

        form.submit(action.method, action.url, {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);

                if (!value) {
                    form.reset();
                    form.clearErrors();
                    setQuery('');
                    setPickerOpen(false);
                }
            }}
        >
            <DialogTrigger asChild>
                <button
                    type="button"
                    className="text-xs text-muted-foreground underline-offset-2 transition-colors hover:text-foreground hover:underline"
                >
                    ¿No es la categoría?
                </button>
            </DialogTrigger>

            <DialogContent>
                <form onSubmit={submit} className="space-y-5">
                    <DialogHeader>
                        <DialogTitle>Cambiar categoría</DialogTitle>
                        <DialogDescription>
                            {transaction.merchant ?? transaction.description}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-2">
                        <Label>Categoría</Label>

                        <Collapsible
                            open={pickerOpen}
                            onOpenChange={setPickerOpen}
                        >
                            <CollapsibleTrigger asChild>
                                <Button
                                    type="button"
                                    variant="outline"
                                    role="combobox"
                                    aria-expanded={pickerOpen}
                                    className="w-full justify-between font-normal"
                                >
                                    {selected ? (
                                        <span className="flex items-center gap-2">
                                            <Dot value={selected} />
                                            {selectedLabel}
                                        </span>
                                    ) : (
                                        <span className="text-muted-foreground">
                                            Selecciona una categoría
                                        </span>
                                    )}
                                    <ChevronsUpDown className="size-4 shrink-0 opacity-50" />
                                </Button>
                            </CollapsibleTrigger>

                            <CollapsibleContent className="mt-2">
                                <Command
                                    loop
                                    className="rounded-lg border border-border"
                                >
                                    <CommandInput
                                        value={query}
                                        onValueChange={setQuery}
                                        placeholder="Busca o escribe una categoría…"
                                    />
                                    <CommandList>
                                        {!canCreate && (
                                            <CommandEmpty>
                                                Escribe para crear una
                                                categoría.
                                            </CommandEmpty>
                                        )}

                                        {canCreate && (
                                            <CommandGroup>
                                                <CommandItem
                                                    value={trimmed}
                                                    onSelect={() =>
                                                        choose(trimmed)
                                                    }
                                                >
                                                    <Plus className="text-muted-foreground" />
                                                    <span>
                                                        Crear «
                                                        <span className="font-medium">
                                                            {trimmed}
                                                        </span>
                                                        »
                                                    </span>
                                                </CommandItem>
                                            </CommandGroup>
                                        )}

                                        {customCategories.length > 0 && (
                                            <CommandGroup heading="Tus categorías">
                                                {customCategories.map(
                                                    (name) => (
                                                        <CommandItem
                                                            key={name}
                                                            value={name}
                                                            onSelect={() =>
                                                                choose(name)
                                                            }
                                                        >
                                                            <Dot value={name} />
                                                            <span className="flex-1">
                                                                {name}
                                                            </span>
                                                            {selected ===
                                                                name && (
                                                                <Check className="text-muted-foreground" />
                                                            )}
                                                        </CommandItem>
                                                    ),
                                                )}
                                            </CommandGroup>
                                        )}

                                        <CommandGroup heading="Categorías">
                                            {categoryOptions.map((option) => (
                                                <CommandItem
                                                    key={option.value}
                                                    value={option.label}
                                                    onSelect={() =>
                                                        choose(option.value)
                                                    }
                                                >
                                                    <Dot value={option.value} />
                                                    <span className="flex-1">
                                                        {option.label}
                                                    </span>
                                                    {selected ===
                                                        option.value && (
                                                        <Check className="text-muted-foreground" />
                                                    )}
                                                </CommandItem>
                                            ))}
                                        </CommandGroup>
                                    </CommandList>
                                </Command>
                            </CollapsibleContent>
                        </Collapsible>
                        <InputError message={form.errors.category} />
                    </div>

                    {transaction.merchant && (
                        <div className="space-y-2">
                            <Label>Aplicar a</Label>
                            <RadioGroup
                                value={form.data.apply_to_all ? 'all' : 'one'}
                                onValueChange={(value) =>
                                    form.setData(
                                        'apply_to_all',
                                        value === 'all',
                                    )
                                }
                            >
                                <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-border p-3 text-sm has-data-[state=checked]:border-brand/50 has-data-[state=checked]:bg-brand/5">
                                    <RadioGroupItem
                                        value="one"
                                        className="mt-0.5"
                                    />
                                    <span>
                                        <span className="font-medium">
                                            Solo esta transacción
                                        </span>
                                    </span>
                                </label>
                                <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-border p-3 text-sm has-data-[state=checked]:border-brand/50 has-data-[state=checked]:bg-brand/5">
                                    <RadioGroupItem
                                        value="all"
                                        className="mt-0.5"
                                    />
                                    <span>
                                        <span className="font-medium">
                                            Todos los movimientos de{' '}
                                            {transaction.merchant}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            Aplica la categoría a cada
                                            movimiento de este comercio.
                                        </span>
                                    </span>
                                </label>
                            </RadioGroup>
                        </div>
                    )}

                    <DialogFooter>
                        <Button
                            type="submit"
                            disabled={form.processing || !form.data.category}
                        >
                            {form.processing ? 'Guardando…' : 'Guardar'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

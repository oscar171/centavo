import { Form } from '@inertiajs/react';
import { FileText, RefreshCw, Upload } from 'lucide-react';
import { useRef, useState } from 'react';
import StatementController from '@/actions/App/Http/Controllers/StatementController';
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
import { cn } from '@/lib/utils';

export default function StatementReuploadDialog({
    statementId,
    variant = 'outline',
}: {
    statementId: string;
    variant?: 'outline' | 'default';
}) {
    const [open, setOpen] = useState(false);
    const [fileName, setFileName] = useState<string | null>(null);
    const [dragging, setDragging] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);

    const reset = () => {
        setFileName(null);
        setDragging(false);

        if (inputRef.current) {
            inputRef.current.value = '';
        }
    };

    const handleDrop = (event: React.DragEvent<HTMLLabelElement>) => {
        event.preventDefault();
        setDragging(false);

        const files = event.dataTransfer.files;

        if (files.length > 0 && inputRef.current) {
            inputRef.current.files = files;
            setFileName(files[0].name);
        }
    };

    return (
        <Dialog
            open={open}
            onOpenChange={(value) => {
                setOpen(value);

                if (!value) {
                    reset();
                }
            }}
        >
            <DialogTrigger asChild>
                <Button variant={variant} size="sm">
                    <RefreshCw className="size-4" />
                    Volver a subir PDF
                </Button>
            </DialogTrigger>

            <DialogContent>
                <Form
                    {...StatementController.reprocess.form(statementId)}
                    onSuccess={() => setOpen(false)}
                    className="space-y-5"
                >
                    {({ processing, errors, progress }) => (
                        <>
                            <DialogHeader>
                                <DialogTitle>Volver a subir el PDF</DialogTitle>
                                <DialogDescription>
                                    Sube nuevamente el estado de cuenta para
                                    intentar procesarlo de nuevo. Reemplazará el
                                    archivo anterior.
                                </DialogDescription>
                            </DialogHeader>

                            <label
                                onDragOver={(event) => {
                                    event.preventDefault();
                                    setDragging(true);
                                }}
                                onDragLeave={() => setDragging(false)}
                                onDrop={handleDrop}
                                className={cn(
                                    'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed border-border p-8 text-center transition-colors hover:border-brand/50',
                                    dragging && 'border-brand/60 bg-brand/5',
                                )}
                            >
                                {fileName ? (
                                    <>
                                        <FileText className="size-6 text-brand" />
                                        <span className="text-sm font-medium">
                                            {fileName}
                                        </span>
                                        <span className="text-xs text-muted-foreground">
                                            Haz clic para cambiar el archivo
                                        </span>
                                    </>
                                ) : (
                                    <>
                                        <Upload className="size-6 text-muted-foreground" />
                                        <span className="text-sm font-medium">
                                            Arrastra el PDF aquí o haz clic para
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
                                            event.target.files?.[0]?.name ??
                                                null,
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

                            <DialogFooter>
                                <Button
                                    type="submit"
                                    disabled={processing || !fileName}
                                >
                                    {processing ? 'Subiendo…' : 'Reprocesar'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

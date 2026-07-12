import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import StatementController from '@/actions/App/Http/Controllers/StatementController';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { Button, buttonVariants } from '@/components/ui/button';

/**
 * Confirmation dialog to delete a statement. Deleting it also removes all of
 * its extracted data (transactions, categories and anomalies) on the server.
 */
export default function DeleteStatementDialog({
    statementId,
    filename,
}: {
    statementId: string;
    filename: string;
}) {
    const [processing, setProcessing] = useState(false);

    const handleDelete = () => {
        router.delete(StatementController.destroy(statementId).url, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
        });
    };

    return (
        <AlertDialog>
            <AlertDialogTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="text-muted-foreground hover:text-destructive"
                    aria-label="Eliminar estado de cuenta"
                >
                    <Trash2 className="size-4" />
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>
                        ¿Eliminar este estado de cuenta?
                    </AlertDialogTitle>
                    <AlertDialogDescription>
                        Se eliminará «{filename}» junto con todos sus
                        movimientos, categorías y anomalías. Esta acción no se
                        puede deshacer.
                    </AlertDialogDescription>
                </AlertDialogHeader>
                <AlertDialogFooter>
                    <AlertDialogCancel disabled={processing}>
                        Cancelar
                    </AlertDialogCancel>
                    <AlertDialogAction
                        onClick={handleDelete}
                        disabled={processing}
                        className={buttonVariants({ variant: 'destructive' })}
                    >
                        Eliminar
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    );
}

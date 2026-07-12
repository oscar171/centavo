import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    status: string;
    label: string;
};

/**
 * Renders a statement status badge following the Centavo design tokens:
 * processed → brand (green), needs_review → amber, failed → destructive,
 * pending/processing → secondary with a spinner.
 */
export default function StatementStatusBadge({ status, label }: Props) {
    if (status === 'processed') {
        return (
            <Badge className="border-transparent bg-brand/10 text-brand">
                {label}
            </Badge>
        );
    }

    if (status === 'needs_review') {
        return (
            <Badge className="border-transparent bg-amber-500/10 text-amber-600 dark:text-amber-400">
                {label}
            </Badge>
        );
    }

    if (status === 'failed') {
        return <Badge variant="destructive">{label}</Badge>;
    }

    return (
        <Badge variant="secondary" className="gap-1.5">
            <Spinner className="size-3" />
            {label}
        </Badge>
    );
}

import { Badge } from '@/components/ui/badge';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    status: string;
    label: string;
};

/**
 * Renders a statement status badge, color-coded by state:
 * processed → brand (green), needs_review → amber, failed → destructive (red),
 * pending/processing → blue with a spinner.
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

    // pending / processing
    return (
        <Badge className="gap-1.5 border-transparent bg-blue-500/10 text-blue-600 dark:text-blue-400">
            <Spinner className="size-3" />
            {label}
        </Badge>
    );
}

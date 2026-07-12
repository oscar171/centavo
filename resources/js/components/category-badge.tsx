const CATEGORY_COLORS: Record<string, string> = {
    income: 'var(--brand)',
    housing: '#6366f1',
    utilities: '#0ea5e9',
    food: '#f59e0b',
    transport: '#14b8a6',
    shopping: '#ec4899',
    entertainment: '#8b5cf6',
    subscriptions: '#f97316',
    health: '#ef4444',
    travel: '#06b6d4',
    transfers: '#64748b',
    fees: '#a855f7',
    other: 'var(--muted-foreground)',
};

// Palette used to give user-created (custom) categories a stable color.
const CUSTOM_PALETTE = [
    '#6366f1',
    '#0ea5e9',
    '#f59e0b',
    '#14b8a6',
    '#ec4899',
    '#8b5cf6',
    '#f97316',
    '#ef4444',
    '#06b6d4',
    '#a855f7',
];

/**
 * The accent color for a transaction category: the predefined color for a known
 * category, a stable hashed color for a custom one, or a neutral tone when the
 * transaction is uncategorized.
 */
export function categoryColor(value: string | null | undefined): string {
    if (!value) {
        return 'var(--muted-foreground)';
    }

    if (CATEGORY_COLORS[value]) {
        return CATEGORY_COLORS[value];
    }

    let hash = 0;

    for (let index = 0; index < value.length; index++) {
        hash = (hash * 31 + value.charCodeAt(index)) >>> 0;
    }

    return CUSTOM_PALETTE[hash % CUSTOM_PALETTE.length];
}

/**
 * A compact pill showing a transaction's category with its accent dot.
 */
export default function CategoryBadge({
    value,
    label,
}: {
    value: string | null;
    label: string | null;
}) {
    return (
        <span className="inline-flex items-center gap-1.5 rounded-md bg-muted px-2 py-0.5 text-xs font-medium whitespace-nowrap">
            <span
                className="size-2 shrink-0 rounded-full"
                style={{ backgroundColor: categoryColor(value) }}
            />
            {label ?? 'Sin categoría'}
        </span>
    );
}

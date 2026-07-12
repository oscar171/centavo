/**
 * Format a monetary amount using the account currency. Amounts arrive from the
 * backend as decimal strings, so they are parsed before formatting.
 */
export function formatCurrency(
    amount: number | string | null | undefined,
    currency = 'USD',
): string {
    const value =
        typeof amount === 'string' ? Number.parseFloat(amount) : amount;

    if (value === null || value === undefined || Number.isNaN(value)) {
        return '—';
    }

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
    }).format(value);
}

/**
 * Format a monetary amount in compact notation (e.g. $16.7K) for chart axes.
 */
export function formatCompactCurrency(
    amount: number | string | null | undefined,
    currency = 'USD',
): string {
    const value =
        typeof amount === 'string' ? Number.parseFloat(amount) : amount;

    if (value === null || value === undefined || Number.isNaN(value)) {
        return '—';
    }

    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency,
        notation: 'compact',
        maximumFractionDigits: 1,
    }).format(value);
}

/**
 * Format an ISO date (YYYY-MM-DD) as a short, locale-aware date. The date is
 * anchored to local midnight to avoid timezone shifts.
 */
export function formatDate(date: string | null | undefined): string {
    if (!date) {
        return '—';
    }

    return new Intl.DateTimeFormat('es', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${date}T00:00:00`));
}

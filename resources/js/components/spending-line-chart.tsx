import {
    CartesianGrid,
    Legend,
    Line,
    LineChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';
import { formatCompactCurrency, formatCurrency } from '@/lib/format';

type ChartLine = { key: string; name: string };

type SeriesPoint = { month: string; label: string } & Record<string, number>;

const COLORS = [
    'var(--chart-1)',
    'var(--chart-2)',
    'var(--chart-3)',
    'var(--chart-4)',
    'var(--chart-5)',
    'var(--brand)',
    'var(--muted-foreground)',
];

function ChartTooltip({
    active,
    payload,
    currency,
    names,
}: {
    active?: boolean;
    payload?: Array<{
        dataKey?: string | number;
        value?: number;
        color?: string;
    }>;
    currency: string;
    names: Record<string, string>;
}) {
    if (!active || !payload || payload.length === 0) {
        return null;
    }

    const rows = payload
        .filter((entry) => Number(entry.value ?? 0) > 0)
        .sort((a, b) => Number(b.value ?? 0) - Number(a.value ?? 0));

    if (rows.length === 0) {
        return null;
    }

    return (
        <div className="rounded-lg border bg-popover px-3 py-2 text-xs shadow-md">
            {rows.map((entry) => (
                <div
                    key={String(entry.dataKey)}
                    className="flex items-center gap-1.5 py-0.5"
                >
                    <span
                        className="size-2 rounded-full"
                        style={{ backgroundColor: entry.color }}
                    />
                    <span className="text-muted-foreground">
                        {names[String(entry.dataKey)] ?? entry.dataKey}:
                    </span>
                    <span className="font-medium tabular-nums">
                        {formatCurrency(Number(entry.value ?? 0), currency)}
                    </span>
                </div>
            ))}
        </div>
    );
}

/**
 * Multi-line chart of spending per category over time. Each top category gets
 * its own line so the user can track how a category's spend evolves across the
 * period; the long tail is grouped server-side into an "Otras" line. An optional
 * per-line color map keeps category colors consistent with the rest of the app;
 * otherwise a default palette is used by position.
 */
export default function SpendingLineChart({
    lines,
    series,
    currency,
    colors,
}: {
    lines: ChartLine[];
    series: SeriesPoint[];
    currency: string;
    colors?: Record<string, string>;
}) {
    const names: Record<string, string> = Object.fromEntries(
        lines.map((line) => [line.key, line.name] as const),
    );

    return (
        <ResponsiveContainer width="100%" height={260}>
            <LineChart
                data={series}
                margin={{ top: 8, right: 8, left: 0, bottom: 0 }}
            >
                <CartesianGrid
                    strokeDasharray="3 3"
                    stroke="currentColor"
                    className="text-muted-foreground/20"
                    vertical={false}
                />
                <XAxis
                    dataKey="label"
                    tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                    tickLine={false}
                    axisLine={false}
                />
                <YAxis
                    tick={{ fontSize: 11, fill: 'var(--muted-foreground)' }}
                    tickLine={false}
                    axisLine={false}
                    width={64}
                    tickFormatter={(value) =>
                        formatCompactCurrency(Number(value), currency)
                    }
                />
                <Tooltip
                    cursor={{ stroke: 'currentColor', opacity: 0.15 }}
                    content={<ChartTooltip currency={currency} names={names} />}
                />
                <Legend
                    iconType="circle"
                    wrapperStyle={{ fontSize: 12 }}
                    formatter={(value) => names[String(value)] ?? value}
                />
                {lines.map((line, index) => {
                    const color =
                        colors?.[line.key] ?? COLORS[index % COLORS.length];

                    return (
                        <Line
                            key={line.key}
                            type="monotone"
                            dataKey={line.key}
                            name={line.key}
                            stroke={color}
                            strokeWidth={2}
                            dot={{ r: 3, fill: color, strokeWidth: 0 }}
                            activeDot={{ r: 5 }}
                        />
                    );
                })}
            </LineChart>
        </ResponsiveContainer>
    );
}

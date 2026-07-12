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

type MonthlyPoint = {
    month: string;
    label: string;
    income: number;
    expense: number;
};

const SERIES = [
    { key: 'income', label: 'Ingresos', color: 'var(--brand)' },
    { key: 'expense', label: 'Gastos', color: 'var(--negative)' },
];

function ChartTooltip({
    active,
    payload,
    currency,
}: {
    active?: boolean;
    payload?: Array<{
        dataKey?: string | number;
        value?: number;
        color?: string;
        payload?: MonthlyPoint;
    }>;
    currency: string;
}) {
    if (!active || !payload || payload.length === 0) {
        return null;
    }

    return (
        <div className="rounded-lg border bg-popover px-3 py-2 text-xs shadow-md">
            <div className="mb-1 font-medium">{payload[0]?.payload?.label}</div>
            {payload.map((entry) => {
                const serie = SERIES.find((s) => s.key === entry.dataKey);

                return (
                    <div
                        key={String(entry.dataKey)}
                        className="flex items-center gap-1.5 py-0.5"
                    >
                        <span
                            className="size-2 rounded-full"
                            style={{ backgroundColor: entry.color }}
                        />
                        <span className="text-muted-foreground">
                            {serie?.label ?? entry.dataKey}:
                        </span>
                        <span className="font-medium tabular-nums">
                            {formatCurrency(Number(entry.value ?? 0), currency)}
                        </span>
                    </div>
                );
            })}
        </div>
    );
}

/**
 * Monthly income vs. expense line chart, styled to match the app tokens.
 */
export default function MonthlyChart({
    data,
    currency,
}: {
    data: MonthlyPoint[];
    currency: string;
}) {
    return (
        <ResponsiveContainer width="100%" height={260}>
            <LineChart
                data={data}
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
                    content={<ChartTooltip currency={currency} />}
                />
                <Legend
                    iconType="circle"
                    wrapperStyle={{ fontSize: 12 }}
                    formatter={(value) =>
                        SERIES.find((s) => s.key === value)?.label ?? value
                    }
                />
                {SERIES.map((serie) => (
                    <Line
                        key={serie.key}
                        type="monotone"
                        dataKey={serie.key}
                        name={serie.key}
                        stroke={serie.color}
                        strokeWidth={2}
                        dot={{ r: 3, fill: serie.color, strokeWidth: 0 }}
                        activeDot={{ r: 5 }}
                    />
                ))}
            </LineChart>
        </ResponsiveContainer>
    );
}

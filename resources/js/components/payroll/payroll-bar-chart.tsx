import { Bar, BarChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

interface BarChartData {
    label: string;
    value: number;
}

interface PayrollBarChartProps {
    data: BarChartData[];
    height?: number;
    horizontal?: boolean;
    formatValue?: (value: number) => string;
}

const defaultFormat = (v: number) =>
    new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP', minimumFractionDigits: 0 }).format(v);

export function PayrollBarChart({ data, height = 250, horizontal = false, formatValue = defaultFormat }: PayrollBarChartProps) {
    if (horizontal) {
        return (
            <ResponsiveContainer width="100%" height={height}>
                <BarChart data={data} layout="vertical" margin={{ left: 80, right: 20, top: 5, bottom: 5 }}>
                    <CartesianGrid strokeDasharray="3 3" stroke="var(--color-border)" horizontal={false} />
                    <XAxis type="number" tick={{ fontSize: 12, fill: 'var(--color-muted-foreground)' }} tickFormatter={formatValue} />
                    <YAxis type="category" dataKey="label" tick={{ fontSize: 12, fill: 'var(--color-muted-foreground)' }} width={76} />
                    <Tooltip
                        formatter={(v: number) => formatValue(v)}
                        contentStyle={{ background: 'var(--color-card)', border: '1px solid var(--color-border)', borderRadius: 8 }}
                    />
                    <Bar dataKey="value" fill="var(--color-blue-brand)" radius={[0, 4, 4, 0]} />
                </BarChart>
            </ResponsiveContainer>
        );
    }

    return (
        <ResponsiveContainer width="100%" height={height}>
            <BarChart data={data} margin={{ left: 10, right: 10, top: 5, bottom: 5 }}>
                <CartesianGrid strokeDasharray="3 3" stroke="var(--color-border)" vertical={false} />
                <XAxis dataKey="label" tick={{ fontSize: 12, fill: 'var(--color-muted-foreground)' }} />
                <YAxis tick={{ fontSize: 12, fill: 'var(--color-muted-foreground)' }} tickFormatter={formatValue} />
                <Tooltip
                    formatter={(v: number) => formatValue(v)}
                    contentStyle={{ background: 'var(--color-card)', border: '1px solid var(--color-border)', borderRadius: 8 }}
                />
                <Bar dataKey="value" fill="var(--color-blue-brand)" radius={[4, 4, 0, 0]} />
            </BarChart>
        </ResponsiveContainer>
    );
}

import { Area, AreaChart, CartesianGrid, Legend, Line, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

interface AreaChartData {
    month: string;
    gross: number;
    base: number;
}

interface PayrollAreaChartProps {
    data: AreaChartData[];
    height?: number;
}

const fmt = (v: number) =>
    new Intl.NumberFormat('es-DO', { style: 'currency', currency: 'DOP', minimumFractionDigits: 0 }).format(v);

export function PayrollAreaChart({ data, height = 260 }: PayrollAreaChartProps) {
    return (
        <ResponsiveContainer width="100%" height={height}>
            <AreaChart data={data} margin={{ left: 10, right: 10, top: 5, bottom: 5 }}>
                <defs>
                    <linearGradient id="grossGradient" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="5%" stopColor="var(--color-blue-brand)" stopOpacity={0.15} />
                        <stop offset="95%" stopColor="var(--color-blue-brand)" stopOpacity={0} />
                    </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" stroke="var(--color-border)" vertical={false} />
                <XAxis dataKey="month" tick={{ fontSize: 12, fill: 'var(--color-muted-foreground)' }} />
                <YAxis tick={{ fontSize: 12, fill: 'var(--color-muted-foreground)' }} tickFormatter={fmt} />
                <Tooltip
                    formatter={(v: number, name: string) => [fmt(v), name === 'gross' ? 'Bruto Total' : 'Salario Base']}
                    contentStyle={{ background: 'var(--color-card)', border: '1px solid var(--color-border)', borderRadius: 8 }}
                />
                <Legend formatter={(v) => (v === 'gross' ? 'Bruto Total' : 'Salario Base')} />
                <Area type="monotone" dataKey="gross" stroke="var(--color-blue-brand)" fill="url(#grossGradient)" strokeWidth={2} dot={{ r: 3 }} />
                <Line type="monotone" dataKey="base" stroke="var(--color-muted-foreground)" strokeDasharray="5 5" strokeWidth={1.5} dot={false} />
            </AreaChart>
        </ResponsiveContainer>
    );
}

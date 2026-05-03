import { Card, CardContent } from '@/components/ui/card';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

interface StatCardProps {
    icon: LucideIcon;
    label: string;
    value: string;
    trend?: string;
    trendPositive?: boolean;
    badge?: ReactNode;
}

export function StatCard({ icon: Icon, label, value, trend, trendPositive, badge }: StatCardProps) {
    return (
        <Card>
            <CardContent className="p-4">
                <div className="flex items-start gap-4">
                    <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-[var(--color-blue-brand)]/10">
                        <Icon className="h-5 w-5 text-[var(--color-blue-brand)]" />
                    </div>
                    <div className="min-w-0 flex-1">
                        <p className="text-2xl font-semibold text-foreground">{value}</p>
                        <p className="text-xs text-muted-foreground">{label}</p>
                        {badge && <div className="mt-1">{badge}</div>}
                        {trend && (
                            <p className={`mt-1 text-xs ${trendPositive ? 'text-[var(--color-green-brand)]' : 'text-destructive'}`}>
                                {trend}
                            </p>
                        )}
                    </div>
                </div>
            </CardContent>
        </Card>
    );
}

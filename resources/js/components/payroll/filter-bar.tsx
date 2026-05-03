import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';

type FilterField =
    | { type: 'select'; key: string; label: string; options: { value: string; label: string }[] }
    | { type: 'date'; key: string; label: string }
    | { type: 'text'; key: string; label: string; placeholder?: string };

interface FilterBarProps {
    filters: FilterField[];
    values: Record<string, string>;
    onChange: (key: string, value: string) => void;
    onClear: () => void;
}

export function FilterBar({ filters, values, onChange, onClear }: FilterBarProps) {
    const hasValues = Object.values(values).some(Boolean);

    return (
        <div className="rounded-lg border border-border bg-card p-4">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {filters.map((f) => {
                    if (f.type === 'select') {
                        return (
                            <div key={f.key}>
                                <label className="mb-1 block text-xs text-muted-foreground">{f.label}</label>
                                <Select value={values[f.key] ?? ''} onValueChange={(v) => onChange(f.key, v === '__all__' ? '' : v)}>
                                    <SelectTrigger>
                                        <SelectValue placeholder={f.label} />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="__all__">Todos</SelectItem>
                                        {f.options.map((o) => (
                                            <SelectItem key={o.value} value={o.value}>
                                                {o.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        );
                    }

                    return (
                        <div key={f.key}>
                            <label className="mb-1 block text-xs text-muted-foreground">{f.label}</label>
                            <Input
                                type={f.type === 'date' ? 'date' : 'text'}
                                value={values[f.key] ?? ''}
                                placeholder={f.type === 'text' ? f.placeholder : undefined}
                                onChange={(e) => onChange(f.key, e.target.value)}
                            />
                        </div>
                    );
                })}
                {hasValues && (
                    <div className="flex items-end">
                        <Button variant="ghost" size="sm" onClick={onClear}>
                            Limpiar
                        </Button>
                    </div>
                )}
            </div>
        </div>
    );
}

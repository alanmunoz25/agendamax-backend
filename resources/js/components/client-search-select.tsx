import * as React from 'react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import type { User } from '@/types/models';

interface ClientSearchSelectProps {
    clients: Pick<User, 'id' | 'name' | 'email' | 'phone'>[];
    value?: number;
    onChange?: (value: number) => void;
    placeholder?: string;
    disabled?: boolean;
}

export function ClientSearchSelect({
    clients,
    value,
    onChange,
    placeholder = 'Select a client',
    disabled = false,
}: ClientSearchSelectProps) {
    const selectedClient = clients.find((client) => client.id === value);

    return (
        <Select
            value={value?.toString()}
            onValueChange={(val) => onChange?.(parseInt(val))}
            disabled={disabled}
        >
            <SelectTrigger>
                <SelectValue placeholder={placeholder}>
                    {selectedClient ? (
                        <span>
                            {selectedClient.name} ({selectedClient.email})
                        </span>
                    ) : (
                        placeholder
                    )}
                </SelectValue>
            </SelectTrigger>
            <SelectContent>
                {clients.map((client) => (
                    <SelectItem key={client.id} value={client.id.toString()}>
                        <div className="flex flex-col">
                            <span className="font-medium">{client.name}</span>
                            <span className="text-xs text-muted-foreground">
                                {client.email}
                            </span>
                        </div>
                    </SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

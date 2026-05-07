import { useState } from 'react';
import { router } from '@inertiajs/react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';

interface ClientWithStats {
    id: number;
    name: string;
    [key: string]: unknown;
}

interface Props {
    client: ClientWithStats | null;
    businessId: number;
    open: boolean;
    onClose: () => void;
}

export function BlockModal({ client, businessId, open, onClose }: Props) {
    const [reason, setReason] = useState('');
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleClose = () => {
        setReason('');
        setIsSubmitting(false);
        onClose();
    };

    const handleSubmit = () => {
        if (!client || reason.length < 10) {
            return;
        }

        setIsSubmitting(true);

        router.post(
            `/api/v1/businesses/${businessId}/clients/${client.id}/block`,
            { reason },
            {
                onSuccess: () => {
                    handleClose();
                    router.reload({ only: ['clients'] });
                },
                onFinish: () => {
                    setIsSubmitting(false);
                },
            }
        );
    };

    if (!client) {
        return null;
    }

    return (
        <Dialog open={open} onOpenChange={(isOpen) => { if (!isOpen) handleClose(); }}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Bloquear cliente</DialogTitle>
                    <DialogDescription>
                        Bloquear a <strong>{client.name}</strong>. El cliente no podrá crear nuevas
                        citas en este negocio, pero su historial de visitas y sellos se mantiene
                        intacto.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-2">
                    <Label htmlFor="block-reason">
                        Motivo del bloqueo <span className="text-destructive">*</span>
                    </Label>
                    <Textarea
                        id="block-reason"
                        placeholder="Describe la razón del bloqueo (mínimo 10 caracteres)..."
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        rows={4}
                        maxLength={500}
                        disabled={isSubmitting}
                    />
                    <p className="text-xs text-muted-foreground text-right">
                        {reason.length}/500
                    </p>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={handleClose} disabled={isSubmitting}>
                        Cancelar
                    </Button>
                    <Button
                        variant="destructive"
                        onClick={handleSubmit}
                        disabled={isSubmitting || reason.length < 10}
                    >
                        {isSubmitting ? 'Bloqueando...' : 'Bloquear cliente'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

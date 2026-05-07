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

export function UnblockModal({ client, businessId, open, onClose }: Props) {
    const [isSubmitting, setIsSubmitting] = useState(false);

    const handleClose = () => {
        setIsSubmitting(false);
        onClose();
    };

    const handleSubmit = () => {
        if (!client) {
            return;
        }

        setIsSubmitting(true);

        router.post(
            `/api/v1/businesses/${businessId}/clients/${client.id}/unblock`,
            {},
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
                    <DialogTitle>Desbloquear cliente</DialogTitle>
                    <DialogDescription>
                        Confirmas que deseas desbloquear a: <strong>{client.name}</strong>
                    </DialogDescription>
                </DialogHeader>

                <p className="text-sm text-muted-foreground">
                    El cliente podrá crear nuevas citas nuevamente.
                </p>

                <DialogFooter>
                    <Button variant="outline" onClick={handleClose} disabled={isSubmitting}>
                        Cancelar
                    </Button>
                    <Button
                        variant="default"
                        onClick={handleSubmit}
                        disabled={isSubmitting}
                    >
                        {isSubmitting ? 'Desbloqueando...' : 'Desbloquear'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

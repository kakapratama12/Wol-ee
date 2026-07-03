import { useEffect, useState } from 'react';
import Modal from '@/Components/ui/modal';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';

type PaymentMethod = 'tunai' | 'qris' | 'transfer';

interface PaymentModalProps {
    open: boolean;
    onClose: () => void;
    total: number;
    submitting: boolean;
    onConfirm: (method: PaymentMethod, amountPaid: number) => void;
}

const methods: { id: PaymentMethod; label: string }[] = [
    { id: 'tunai', label: 'Tunai' },
    { id: 'qris', label: 'QRIS' },
    { id: 'transfer', label: 'Transfer' },
];

export default function PaymentModal({
    open,
    onClose,
    total,
    submitting,
    onConfirm,
}: PaymentModalProps) {
    const [method, setMethod] = useState<PaymentMethod>('tunai');
    const [amountPaid, setAmountPaid] = useState(() => String(Math.ceil(total)));

    useEffect(() => {
        if (open) {
            setMethod('tunai');
            setAmountPaid(String(Math.ceil(total)));
        }
    }, [open, total]);

    const paid = Number(amountPaid) || 0;
    const change = method === 'tunai' ? Math.max(0, paid - total) : 0;
    const canSubmit =
        method === 'tunai' ? paid >= total : true;

    return (
        <Modal open={open} onClose={onClose} title="Pembayaran" size="lg">
            <p className="mb-4 text-sm text-muted-foreground">
                Total: <span className="text-lg font-bold text-foreground">{formatRupiah(total)}</span>
            </p>

            <div className="mb-4 grid grid-cols-3 gap-2">
                {methods.map((m) => (
                    <button
                        key={m.id}
                        type="button"
                        onClick={() => setMethod(m.id)}
                        className={cn(
                            'h-12 rounded-lg border text-sm font-medium',
                            method === m.id
                                ? 'border-primary bg-primary text-primary-foreground'
                                : 'border-border bg-background hover:bg-accent',
                        )}
                    >
                        {m.label}
                    </button>
                ))}
            </div>

            {method === 'tunai' && (
                <div className="space-y-3">
                    <div>
                        <Label htmlFor="amount_paid">Nominal diterima</Label>
                        <Input
                            id="amount_paid"
                            type="number"
                            min={0}
                            className="mt-1 h-12 text-lg"
                            value={amountPaid}
                            onChange={(e) => setAmountPaid(e.target.value)}
                        />
                    </div>
                    <p className="text-sm">
                        Kembalian: <span className="font-semibold">{formatRupiah(change)}</span>
                    </p>
                </div>
            )}

            {method === 'qris' && (
                <p className="rounded-lg bg-muted p-3 text-sm text-muted-foreground">
                    Customer scan QR statis, lalu konfirmasi setelah pembayaran diterima.
                </p>
            )}

            {method === 'transfer' && (
                <p className="rounded-lg bg-muted p-3 text-sm text-muted-foreground">
                    Pastikan transfer sudah masuk, lalu konfirmasi.
                </p>
            )}

            <div className="mt-6 flex gap-3">
                <Button type="button" variant="outline" className="flex-1" onClick={onClose}>
                    Batal
                </Button>
                <Button
                    type="button"
                    className="flex-1 h-12"
                    disabled={!canSubmit || submitting}
                    onClick={() => onConfirm(method, method === 'tunai' ? paid : total)}
                >
                    {method === 'tunai' ? 'Selesai' : 'Konfirmasi Diterima'}
                </Button>
            </div>
        </Modal>
    );
}

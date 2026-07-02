import { FormEventHandler } from 'react';
import { Head, useForm } from '@inertiajs/react';
import PosLayout from '@/Layouts/PosLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { formatRupiah } from '@/lib/format';

interface SessionInfo {
    opening_cash: number;
    total_cash: number;
    total_qris: number;
    total_transfer: number;
    expected_cash: number;
    outlet?: string | null;
}

interface SalesLine {
    product: string;
    quantity: number;
    revenue: number;
}

interface Props {
    session: SessionInfo;
    salesSummary: SalesLine[];
    orderCount: number;
}

export default function CloseSession({ session, salesSummary, orderCount }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        actual_cash: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/pos/session/close');
    };

    const totalOmset = session.total_cash + session.total_qris + session.total_transfer;

    return (
        <PosLayout title="Tutup Sesi" branch={session.outlet}>
            <Head title="Tutup Sesi Kasir" />

            <div className="mx-auto max-w-md space-y-4">
                <div className="rounded-xl border border-border bg-card p-4 text-sm">
                    <div className="flex justify-between py-1">
                        <span>Modal awal</span>
                        <span>{formatRupiah(session.opening_cash)}</span>
                    </div>
                    <div className="flex justify-between py-1">
                        <span>Penjualan tunai</span>
                        <span>{formatRupiah(session.total_cash)}</span>
                    </div>
                    <div className="flex justify-between py-1 text-muted-foreground">
                        <span>QRIS (tidak di laci)</span>
                        <span>{formatRupiah(session.total_qris)}</span>
                    </div>
                    <div className="flex justify-between py-1 text-muted-foreground">
                        <span>Transfer (tidak di laci)</span>
                        <span>{formatRupiah(session.total_transfer)}</span>
                    </div>
                    <div className="mt-2 flex justify-between border-t border-border pt-2 font-semibold">
                        <span>Total omset ({orderCount} transaksi)</span>
                        <span>{formatRupiah(totalOmset)}</span>
                    </div>
                    <div className="mt-2 flex justify-between border-t border-border pt-2 font-semibold">
                        <span>Kas di laci seharusnya</span>
                        <span>{formatRupiah(session.expected_cash)}</span>
                    </div>
                </div>

                {salesSummary.length > 0 && (
                    <div className="rounded-xl border border-border bg-card p-4 text-sm">
                        <h3 className="mb-2 font-semibold">Ringkasan penjualan</h3>
                        <ul className="space-y-1">
                            {salesSummary.map((line) => (
                                <li key={line.product} className="flex justify-between gap-2">
                                    <span>
                                        {line.product} × {line.quantity}
                                    </span>
                                    <span>{formatRupiah(line.revenue)}</span>
                                </li>
                            ))}
                        </ul>
                    </div>
                )}

                <form onSubmit={submit} className="rounded-xl border border-border bg-card p-4">
                    <Label htmlFor="actual_cash">Kas aktual (hitung fisik)</Label>
                    <Input
                        id="actual_cash"
                        type="number"
                        min={0}
                        className="mt-1 h-12 text-lg"
                        value={data.actual_cash}
                        onChange={(e) => setData('actual_cash', e.target.value)}
                        placeholder="Hitung uang tunai di laci"
                    />
                    {errors.actual_cash && (
                        <p className="mt-1 text-sm text-destructive">{errors.actual_cash}</p>
                    )}
                    <Button
                        type="button"
                        variant="outline"
                        className="mt-2 w-full"
                        onClick={() => setData('actual_cash', String(session.expected_cash))}
                    >
                        Sama dengan seharusnya
                    </Button>
                    <Button type="submit" disabled={processing} className="mt-3 h-12 w-full">
                        Tutup Sesi
                    </Button>
                </form>
            </div>
        </PosLayout>
    );
}

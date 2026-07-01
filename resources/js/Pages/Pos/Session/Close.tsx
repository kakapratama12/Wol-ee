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
    branch?: string | null;
}

interface Props {
    session: SessionInfo;
}

export default function CloseSession({ session }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        actual_cash: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/pos/session/close');
    };

    const totalOmset = session.total_cash + session.total_qris + session.total_transfer;

    return (
        <PosLayout title="Tutup Sesi" branch={session.branch}>
            <Head title="Tutup Sesi Kasir" />

            <div className="mx-auto max-w-md space-y-4">
                <div className="rounded-xl border border-border bg-card p-4 text-sm">
                    <div className="flex justify-between py-1">
                        <span>Modal awal</span>
                        <span>{formatRupiah(session.opening_cash)}</span>
                    </div>
                    <div className="flex justify-between py-1">
                        <span>Tunai</span>
                        <span>{formatRupiah(session.total_cash)}</span>
                    </div>
                    <div className="flex justify-between py-1">
                        <span>QRIS</span>
                        <span>{formatRupiah(session.total_qris)}</span>
                    </div>
                    <div className="flex justify-between py-1">
                        <span>Transfer</span>
                        <span>{formatRupiah(session.total_transfer)}</span>
                    </div>
                    <div className="mt-2 flex justify-between border-t border-border pt-2 font-semibold">
                        <span>Total omset</span>
                        <span>{formatRupiah(totalOmset)}</span>
                    </div>
                    <div className="flex justify-between py-1 text-muted-foreground">
                        <span>Kas seharusnya</span>
                        <span>{formatRupiah(session.expected_cash)}</span>
                    </div>
                </div>

                <form onSubmit={submit} className="rounded-xl border border-border bg-card p-4">
                    <Label htmlFor="actual_cash">Kas aktual (hitung fisik)</Label>
                    <Input
                        id="actual_cash"
                        type="number"
                        min={0}
                        className="mt-1 h-12 text-lg"
                        value={data.actual_cash}
                        onChange={(e) => setData('actual_cash', e.target.value)}
                    />
                    {errors.actual_cash && (
                        <p className="mt-1 text-sm text-destructive">{errors.actual_cash}</p>
                    )}
                    <Button type="submit" disabled={processing} className="mt-4 h-12 w-full">
                        Tutup Sesi
                    </Button>
                </form>
            </div>
        </PosLayout>
    );
}

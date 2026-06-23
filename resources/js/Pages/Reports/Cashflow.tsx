import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Plus, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { CurrencyInput } from '@/Components/ui/currency-input';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select } from '@/Components/ui/select';
import { formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';

interface KasMasukDetail {
    penjualan: number;
    modal: number;
}

interface KasKeluarDetail {
    pembelian: number;
    biaya_operasional: number;
    di_luar_usaha: number;
}

interface Report {
    saldo_awal: number;
    kas_masuk: KasMasukDetail;
    total_kas_masuk: number;
    kas_keluar: KasKeluarDetail;
    total_kas_keluar: number;
    saldo_akhir: number;
}

interface Props {
    report: Report;
    period: { month: number; year: number };
    periodLabel: string;
}

const months = [
    'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
];

const modalTypes = [
    { value: 'modal_awal', label: 'Modal Awal' },
    { value: 'modal_tambahan', label: 'Modal Tambahan' },
    { value: 'lainnya', label: 'Lainnya' },
];

export default function Cashflow({ report, period, periodLabel }: Props) {
    const years = Array.from({ length: 5 }, (_, i) => new Date().getFullYear() - i);
    const [showModal, setShowModal] = useState(false);
    const form = useForm({
        type: 'modal_awal',
        description: '',
        amount: '',
        occurred_at: new Date().toISOString().slice(0, 10),
    });

    const changePeriod = (month: number, year: number) => {
        router.get('/reports/cashflow', { month, year }, { preserveState: true, preserveScroll: true });
    };

    const submitModal = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/cash-entries', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setShowModal(false);
            },
        });
    };

    return (
        <AppLayout title="Laporan Cashflow">
            <Head title="Laporan Cashflow" />

            <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                <div className="flex gap-2">
                    <Select
                        className="w-40"
                        value={period.month}
                        onChange={(e) => changePeriod(Number(e.target.value), period.year)}
                    >
                        {months.map((m, i) => (
                            <option key={i} value={i + 1}>
                                {m}
                            </option>
                        ))}
                    </Select>
                    <Select className="w-28" value={period.year} onChange={(e) => changePeriod(period.month, Number(e.target.value))}>
                        {years.map((y) => (
                            <option key={y} value={y}>
                                {y}
                            </option>
                        ))}
                    </Select>
                </div>
                <Button variant="outline" onClick={() => setShowModal(!showModal)}>
                    <Plus className="h-4 w-4" /> Catat Kas Masuk
                </Button>
            </div>

            {showModal && (
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>Catat Kas Masuk (Modal)</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submitModal} className="flex flex-wrap items-end gap-4">
                            <div>
                                <Label>Jenis</Label>
                                <Select value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                                    {modalTypes.map((t) => (
                                        <option key={t.value} value={t.value}>
                                            {t.label}
                                        </option>
                                    ))}
                                </Select>
                            </div>
                            <div>
                                <Label>Deskripsi</Label>
                                <Input value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} className="w-48" />
                            </div>
                            <div>
                                <Label>Jumlah (Rp)</Label>
                                <CurrencyInput value={form.data.amount} onChange={(v) => form.setData('amount', v)} className="w-40" />
                                {form.errors.amount && <p className="mt-1 text-xs text-destructive">{form.errors.amount}</p>}
                            </div>
                            <div>
                                <Label>Tanggal</Label>
                                <Input type="date" value={form.data.occurred_at} onChange={(e) => form.setData('occurred_at', e.target.value)} />
                            </div>
                            <Button type="submit" disabled={form.processing}>
                                <Plus className="h-4 w-4" /> Simpan
                            </Button>
                            <Button type="button" variant="ghost" onClick={() => setShowModal(false)}>
                                Batal
                            </Button>
                        </form>
                    </CardContent>
                </Card>
            )}

            <Card className="mx-auto max-w-2xl">
                <CardHeader>
                    <CardTitle>Laporan Cashflow — {periodLabel}</CardTitle>
                </CardHeader>
                <CardContent className="space-y-4 text-sm">
                    {/* Saldo Awal */}
                    <SectionTitle title="Saldo Kas Awal" />
                    <Line label="Saldo awal bulan" value={formatRupiah(report.saldo_awal)} bold />

                    <div className="border-t border-border" />

                    {/* Kas Masuk */}
                    <SectionTitle title="Kas Masuk" color="text-green-700" />
                    <Line label="Penjualan" value={formatRupiah(report.kas_masuk.penjualan)} />
                    <Line label="Modal / Kas Masuk Lain" value={formatRupiah(report.kas_masuk.modal)} />
                    <Line label="Total Kas Masuk" value={formatRupiah(report.total_kas_masuk)} bold />

                    <div className="border-t border-border" />

                    {/* Kas Keluar */}
                    <SectionTitle title="Kas Keluar" color="text-red-700" />
                    <Line label="Pembelian Bahan" value={`(${formatRupiah(report.kas_keluar.pembelian)})`} />
                    <Line label="Biaya Operasional" value={`(${formatRupiah(report.kas_keluar.biaya_operasional)})`} />
                    <Line label="Di Luar Usaha" value={`(${formatRupiah(report.kas_keluar.di_luar_usaha)})`} muted />
                    <Line label="Total Kas Keluar" value={`(${formatRupiah(report.total_kas_keluar)})`} bold />

                    <div className="border-t border-border" />

                    {/* Saldo Akhir */}
                    <SectionTitle title="Saldo Kas Akhir" />
                    <Line
                        label="Saldo Akhir"
                        value={formatRupiah(report.saldo_akhir)}
                        bold
                        big
                        negative={report.saldo_akhir < 0}
                    />
                </CardContent>
            </Card>
        </AppLayout>
    );
}

function SectionTitle({ title, color }: { title: string; color?: string }) {
    return <p className={cn('font-semibold', color ?? 'text-foreground')}>{title}</p>;
}

function Line({
    label,
    value,
    bold,
    muted,
    big,
    negative,
}: {
    label: string;
    value: string;
    bold?: boolean;
    muted?: boolean;
    big?: boolean;
    negative?: boolean;
}) {
    return (
        <div className="flex items-center justify-between py-0.5">
            <span className={muted ? 'text-muted-foreground' : ''}>{label}</span>
            <span
                className={cn(
                    bold && 'font-bold',
                    big && 'text-lg',
                    negative ? 'text-destructive' : big ? 'text-success' : '',
                )}
            >
                {value}
            </span>
        </div>
    );
}
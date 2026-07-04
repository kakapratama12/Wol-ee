import AppLayout from '@/Layouts/AppLayout';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';

interface PayableItem {
    id: number;
    description: string;
    quantity: number;
    unit_price: number;
    total: number;
}

interface Payable {
    id: number;
    payable_number: string;
    partner: { id: number; name: string };
    amount: number;
    paid_amount: number;
    due_date: string | null;
    status: string;
    note: string | null;
    paid_at: string | null;
    items: PayableItem[];
    created_at: string;
}

function formatRupiah(amount: number): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
}

function formatDate(date: string | null): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function StatusBadge({ status }: { status: string }) {
    const styles: Record<string, string> = {
        outstanding: 'bg-yellow-100 text-yellow-800',
        partial: 'bg-blue-100 text-blue-800',
        paid: 'bg-green-100 text-green-800',
        draft: 'bg-gray-100 text-gray-600',
    };

    const labels: Record<string, string> = {
        outstanding: 'Belum Lunas',
        partial: 'Sebagian',
        paid: 'Lunas',
        draft: 'Draft',
    };

    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${styles[status] || styles.draft}`}>
            {labels[status] || status}
        </span>
    );
}

export default function Show({
    payable,
    remaining,
}: {
    payable: Payable;
    remaining: number;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        amount: '',
        paid_at: '',
    });

    const submitPayment = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('payables.pay', payable.id), {
            onSuccess: () => reset(),
        });
    };

    const isPayable = remaining > 0;

    return (
        <AppLayout title={`Tagihan ${payable.payable_number}`}>
            <Head title={`Tagihan ${payable.payable_number}`} />

            <div className="mb-4">
                <Link
                    href="/payables"
                    className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Kembali
                </Link>
            </div>

            <div className="grid gap-6 lg:grid-cols-3 *:min-w-0">
                {/* Detail Tagihan */}
                <div className="lg:col-span-2 space-y-6">
                    <div className="rounded-lg border border-border bg-card p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h3 className="text-lg font-semibold">{payable.payable_number}</h3>
                            <StatusBadge status={payable.status} />
                        </div>

                        <dl className="grid grid-cols-2 gap-4 *:min-w-0 text-sm">
                            <div>
                                <dt className="text-muted-foreground">Supplier</dt>
                                <dd className="font-medium">{payable.partner.name}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Jatuh Tempo</dt>
                                <dd className="font-medium">{formatDate(payable.due_date)}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Total Tagihan</dt>
                                <dd className="font-medium">{formatRupiah(payable.amount)}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Sudah Dibayar</dt>
                                <dd className="font-medium">{formatRupiah(payable.paid_amount)}</dd>
                            </div>
                            <div>
                                <dt className="text-muted-foreground">Sisa Tagihan</dt>
                                <dd className="text-lg font-bold text-primary">{formatRupiah(remaining)}</dd>
                            </div>
                            {payable.note && (
                                <div className="col-span-2">
                                    <dt className="text-muted-foreground">Catatan</dt>
                                    <dd>{payable.note}</dd>
                                </div>
                            )}
                        </dl>
                    </div>

                    {/* Line Items */}
                    <div className="rounded-lg border border-border bg-card p-6">
                        <h4 className="mb-4 font-medium">Detail Item</h4>
                        <table className="w-full text-sm">
                            <thead>
                                <tr className="border-b border-border">
                                    <th className="pb-2 text-left font-medium text-muted-foreground">Deskripsi</th>
                                    <th className="pb-2 text-right font-medium text-muted-foreground">Qty</th>
                                    <th className="pb-2 text-right font-medium text-muted-foreground">Harga</th>
                                    <th className="pb-2 text-right font-medium text-muted-foreground">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {payable.items.map((item) => (
                                    <tr key={item.id} className="border-b border-border last:border-0">
                                        <td className="py-2">{item.description}</td>
                                        <td className="py-2 text-right">{item.quantity}</td>
                                        <td className="py-2 text-right">{formatRupiah(item.unit_price)}</td>
                                        <td className="py-2 text-right font-medium">{formatRupiah(item.total)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>

                {/* Sidebar: Form Pembayaran */}
                <div className="space-y-6">
                    {isPayable && (
                        <div className="rounded-lg border border-border bg-card p-6">
                            <h4 className="mb-4 font-medium">Catat Pembayaran</h4>
                            <form onSubmit={submitPayment} className="space-y-4">
                                <div>
                                    <InputLabel htmlFor="amount" value="Jumlah Bayar" />
                                    <TextInput
                                        id="amount"
                                        type="number"
                                        value={data.amount}
                                        onChange={(e) => setData('amount', e.target.value)}
                                        className="mt-1 block w-full"
                                        placeholder={`Maks: ${formatRupiah(remaining)}`}
                                        max={remaining}
                                    />
                                    <InputError message={errors.amount} className="mt-2" />
                                </div>

                                <div>
                                    <InputLabel htmlFor="paid_at" value="Tanggal Bayar" />
                                    <TextInput
                                        id="paid_at"
                                        type="date"
                                        value={data.paid_at}
                                        onChange={(e) => setData('paid_at', e.target.value)}
                                        className="mt-1 block w-full"
                                    />
                                    <InputError message={errors.paid_at} className="mt-2" />
                                </div>

                                <PrimaryButton disabled={processing}>
                                    {processing ? 'Menyimpan...' : 'Bayar'}
                                </PrimaryButton>
                            </form>
                        </div>
                    )}

                    {payable.status === 'paid' && (
                        <div className="rounded-lg border border-green-200 bg-green-50 p-4 text-center">
                            <p className="text-sm font-medium text-green-800">✓ Tagihan sudah lunas</p>
                            {payable.paid_at && (
                                <p className="mt-1 text-xs text-green-600">
                                    Dibayar {formatDate(payable.paid_at)}
                                </p>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

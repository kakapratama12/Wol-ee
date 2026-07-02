import { Head, Link } from '@inertiajs/react';
import PosLayout from '@/Layouts/PosLayout';
import { formatRupiah } from '@/lib/format';

interface SaleLine {
    product: string | null;
    quantity: number;
    revenue: number;
}

interface OrderInfo {
    id: number;
    total: number;
    payment_method: string;
    amount_paid: number;
    change_amount: number;
    outlet?: string | null;
    created_at?: string | null;
    sales: SaleLine[];
}

interface Props {
    order: OrderInfo;
}

const paymentLabels: Record<string, string> = {
    tunai: 'Tunai',
    qris: 'QRIS',
    transfer: 'Transfer',
};

export default function OrderSuccess({ order }: Props) {
    return (
        <PosLayout title="Transaksi Berhasil" branch={order.outlet}>
            <Head title="Transaksi Berhasil" />

            <div className="mx-auto max-w-md rounded-xl border border-border bg-card p-6 text-center shadow-sm">
                <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-teal-100 text-2xl text-teal-700 dark:bg-teal-900/40 dark:text-teal-300">
                    ✓
                </div>
                <h2 className="text-xl font-semibold">Pembayaran berhasil</h2>
                <p className="mt-1 text-sm text-muted-foreground">Order #{order.id}</p>

                <div className="mt-6 space-y-2 text-left text-sm">
                    {order.sales.map((line, i) => (
                        <div key={i} className="flex justify-between gap-2">
                            <span>
                                {line.product} × {line.quantity}
                            </span>
                            <span>{formatRupiah(line.revenue)}</span>
                        </div>
                    ))}
                    <div className="flex justify-between border-t border-border pt-2 font-semibold">
                        <span>Total</span>
                        <span>{formatRupiah(order.total)}</span>
                    </div>
                    <div className="flex justify-between text-muted-foreground">
                        <span>Metode</span>
                        <span>{paymentLabels[order.payment_method] ?? order.payment_method}</span>
                    </div>
                    {order.payment_method === 'tunai' && (
                        <div className="flex justify-between text-muted-foreground">
                            <span>Kembalian</span>
                            <span>{formatRupiah(order.change_amount)}</span>
                        </div>
                    )}
                </div>

                <div className="mt-6 flex flex-col gap-3">
                    <Link
                        href="/pos/register"
                        className="inline-flex h-12 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                    >
                        Transaksi Baru
                    </Link>
                </div>
            </div>
        </PosLayout>
    );
}

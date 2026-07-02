import { Head } from '@inertiajs/react';
import { Clock, Banknote, QrCode, ArrowRightLeft } from 'lucide-react';
import PosLayout from '@/Layouts/PosLayout';
import { formatRupiah } from '@/lib/format';

interface Order {
    id: number;
    time: string;
    items: string;
    total: number;
    payment: string;
}

interface Props {
    session: { id: number; opened_at: string } | null;
    summary: {
        total_orders: number;
        total_revenue: number;
        total_items: number;
        cash: number;
        qris: number;
        transfer: number;
    };
    recentOrders: Order[];
}

export default function Today({ session, summary, recentOrders }: Props) {
    return (
        <PosLayout title="Hari Ini">
            <Head title="Penjualan Hari Ini" />

            <div className="mx-auto max-w-2xl space-y-6">
                {/* Summary Cards */}
                <div className="grid grid-cols-2 gap-4">
                    <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
                        <p className="text-xs text-muted-foreground">Total Omset</p>
                        <p className="mt-1 text-2xl font-bold text-primary">{formatRupiah(summary.total_revenue)}</p>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
                        <p className="text-xs text-muted-foreground">Transaksi</p>
                        <p className="mt-1 text-2xl font-bold">{summary.total_orders}</p>
                    </div>
                </div>

                {/* Payment Breakdown */}
                <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <h3 className="mb-3 text-sm font-semibold text-muted-foreground">Pembayaran</h3>
                    <div className="grid grid-cols-3 gap-3 text-center">
                        <div>
                            <Banknote className="mx-auto mb-1 h-5 w-5 text-green-600 dark:text-green-400" />
                            <p className="text-sm font-semibold">{formatRupiah(summary.cash)}</p>
                            <p className="text-xs text-muted-foreground">Tunai</p>
                        </div>
                        <div>
                            <QrCode className="mx-auto mb-1 h-5 w-5 text-blue-600 dark:text-blue-400" />
                            <p className="text-sm font-semibold">{formatRupiah(summary.qris)}</p>
                            <p className="text-xs text-muted-foreground">QRIS</p>
                        </div>
                        <div>
                            <ArrowRightLeft className="mx-auto mb-1 h-5 w-5 text-amber-600 dark:text-amber-400" />
                            <p className="text-sm font-semibold">{formatRupiah(summary.transfer)}</p>
                            <p className="text-xs text-muted-foreground">Transfer</p>
                        </div>
                    </div>
                </div>

                {/* Recent Orders */}
                <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
                    <h3 className="mb-3 text-sm font-semibold text-muted-foreground">Transaksi Terakhir</h3>
                    {recentOrders.length === 0 ? (
                        <p className="py-4 text-center text-sm text-muted-foreground">Belum ada transaksi hari ini.</p>
                    ) : (
                        <div className="space-y-2">
                            {recentOrders.map((order) => (
                                <div
                                    key={order.id}
                                    className="flex items-center justify-between rounded-lg border border-border p-3"
                                >
                                    <div className="flex items-center gap-3">
                                        <div className="flex h-10 w-10 items-center justify-center rounded-full bg-muted">
                                            <Clock className="h-4 w-4 text-muted-foreground" />
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium">{order.items || 'Item'}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {order.time} · {order.payment}
                                            </p>
                                        </div>
                                    </div>
                                    <p className="text-sm font-semibold">{formatRupiah(order.total)}</p>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </PosLayout>
    );
}

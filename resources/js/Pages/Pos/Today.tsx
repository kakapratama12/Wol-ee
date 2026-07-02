import { Head } from '@inertiajs/react';
import { BarChart3, ShoppingCart, DollarSign } from 'lucide-react';
import PosLayout from '@/Layouts/PosLayout';
import { formatRupiah } from '@/lib/format';

interface SaleItem {
    id: number;
    product: string;
    quantity: number;
    revenue: number;
    occurred_at: string;
}

interface Props {
    todayRevenue: number;
    todayTransactions: number;
    todayItemsSold: number;
    recentSales: SaleItem[];
    outlet: string;
}

export default function TodaySummary({
    todayRevenue,
    todayTransactions,
    todayItemsSold,
    recentSales,
    outlet,
}: Props) {
    return (
        <PosLayout title="Hari Ini" branch={outlet} activeTab="hari-ini">
            <Head title="Ringkasan Hari Ini" />

            {/* Stats Cards */}
            <div className="mb-6 grid grid-cols-3 gap-3">
                <div className="rounded-xl border border-border bg-card p-4 text-center shadow-sm">
                    <DollarSign className="mx-auto mb-2 h-6 w-6 text-green-600" />
                    <p className="text-xl font-bold">{formatRupiah(todayRevenue)}</p>
                    <p className="text-xs text-muted-foreground">Omset</p>
                </div>
                <div className="rounded-xl border border-border bg-card p-4 text-center shadow-sm">
                    <ShoppingCart className="mx-auto mb-2 h-6 w-6 text-blue-600" />
                    <p className="text-xl font-bold">{todayTransactions}</p>
                    <p className="text-xs text-muted-foreground">Transaksi</p>
                </div>
                <div className="rounded-xl border border-border bg-card p-4 text-center shadow-sm">
                    <BarChart3 className="mx-auto mb-2 h-6 w-6 text-purple-600" />
                    <p className="text-xl font-bold">{todayItemsSold}</p>
                    <p className="text-xs text-muted-foreground">Item Terjual</p>
                </div>
            </div>

            {/* Recent Sales */}
            <div className="rounded-xl border border-border bg-card p-4 shadow-sm">
                <h3 className="mb-3 text-sm font-semibold text-muted-foreground">Penjualan Terbaru</h3>
                {recentSales.length === 0 ? (
                    <p className="py-6 text-center text-sm text-muted-foreground">
                        Belum ada penjualan hari ini.
                    </p>
                ) : (
                    <div className="space-y-2">
                        {recentSales.map((sale) => (
                            <div
                                key={sale.id}
                                className="flex items-center justify-between rounded-lg border border-border px-3 py-2"
                            >
                                <div>
                                    <p className="text-sm font-medium">{sale.product}</p>
                                    <p className="text-xs text-muted-foreground">
                                        {sale.quantity} item ·{' '}
                                        {new Date(sale.occurred_at).toLocaleTimeString('id-ID', {
                                            hour: '2-digit',
                                            minute: '2-digit',
                                        })}
                                    </p>
                                </div>
                                <span className="text-sm font-semibold">{formatRupiah(sale.revenue)}</span>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </PosLayout>
    );
}

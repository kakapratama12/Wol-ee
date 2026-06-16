import { Head, Link } from '@inertiajs/react';
import { Banknote, TrendingUp, Percent, PackageMinus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import StatCard from '@/Components/StatCard';
import StockStatusBadge from '@/Components/StockStatusBadge';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { formatRupiah, formatPercent, formatNumber } from '@/lib/format';

interface Metrics {
    revenue: number;
    cogs: number;
    gross_profit: number;
    gross_margin: number;
    net_profit: number;
}

interface LowStock {
    id: number;
    name: string;
    current_stock: number;
    minimum_stock: number;
    base_unit: string;
    status: string;
}

interface RecentSale {
    id: number;
    product: string | null;
    quantity: number;
    revenue: number;
    profit: number;
    margin: number;
}

interface Props {
    month: string;
    metrics: Metrics;
    lowStock: LowStock[];
    recentSales: RecentSale[];
}

export default function Dashboard({ month, metrics, lowStock, recentSales }: Props) {
    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />

            <p className="mb-4 text-sm text-muted-foreground">Ringkasan bisnis - {month}</p>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard label="Omset" value={formatRupiah(metrics.revenue)} icon={<Banknote className="h-5 w-5" />} />
                <StatCard label="COGS" value={formatRupiah(metrics.cogs)} accent="warning" icon={<PackageMinus className="h-5 w-5" />} />
                <StatCard
                    label="Laba Kotor"
                    value={formatRupiah(metrics.gross_profit)}
                    hint={`Margin ${formatPercent(metrics.gross_margin)}`}
                    accent="success"
                    icon={<TrendingUp className="h-5 w-5" />}
                />
                <StatCard
                    label="Laba Bersih"
                    value={formatRupiah(metrics.net_profit)}
                    accent="default"
                    icon={<Percent className="h-5 w-5" />}
                />
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Stok Perlu Perhatian</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {lowStock.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">Semua stok aman.</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Bahan</TableHead>
                                        <TableHead>Stok</TableHead>
                                        <TableHead>Min</TableHead>
                                        <TableHead>Status</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {lowStock.map((s) => (
                                        <TableRow key={s.id}>
                                            <TableCell className="font-medium">{s.name}</TableCell>
                                            <TableCell>
                                                {formatNumber(s.current_stock, 2)} {s.base_unit}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {formatNumber(s.minimum_stock, 2)} {s.base_unit}
                                            </TableCell>
                                            <TableCell>
                                                <StockStatusBadge status={s.status} />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                        <Link href="/inventory" className="mt-3 inline-block text-sm font-medium text-primary hover:underline">
                            Lihat semua inventory
                        </Link>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Penjualan Terbaru</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recentSales.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">Belum ada penjualan.</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Produk</TableHead>
                                        <TableHead>Qty</TableHead>
                                        <TableHead>Revenue</TableHead>
                                        <TableHead>Profit</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recentSales.map((s) => (
                                        <TableRow key={s.id}>
                                            <TableCell className="font-medium">{s.product ?? '-'}</TableCell>
                                            <TableCell>{s.quantity}</TableCell>
                                            <TableCell>{formatRupiah(s.revenue)}</TableCell>
                                            <TableCell className="text-success">{formatRupiah(s.profit)}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                        <Link href="/sales" className="mt-3 inline-block text-sm font-medium text-primary hover:underline">
                            Lihat semua penjualan
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

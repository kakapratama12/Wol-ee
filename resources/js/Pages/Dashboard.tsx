import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Banknote, TrendingUp, Percent, PackageMinus, ShoppingCart, Package, BarChart3 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import StatCard from '@/Components/StatCard';
import StockStatusBadge from '@/Components/StockStatusBadge';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Input } from '@/Components/ui/input';
import { Select } from '@/Components/ui/select';
import { formatRupiah, formatPercent, formatNumber, formatDate } from '@/lib/format';
import { cn } from '@/lib/utils';

/* ---------- Shared interfaces ---------- */

interface RecentSale {
    id: number;
    product: string | null;
    quantity: number;
    revenue: number;
    occurred_at: string | null;
}

/* ---------- Pengelola interfaces ---------- */

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

interface PengelolaRecentSale extends RecentSale {
    profit: number;
    margin: number;
}

interface RecentPurchase {
    id: number;
    ingredient: string | null;
    base_unit: string | null;
    quantity: number;
    total: number;
    source: string;
    occurred_at: string | null;
}

interface MonthlyChartPoint {
    label: string;
    month: number;
    year: number;
    revenue: number;
    expense: number;
}

interface UpcomingPayable {
    id: number;
    payable_number: string;
    partner: string;
    remaining: number;
    due_date: string | null;
}

interface OutletOption {
    id: number;
    name: string;
}

/* ---------- Staff interfaces ---------- */

interface OutletInventoryItem {
    id: number;
    ingredient_name: string;
    quantity: number;
    unit: string;
    base_unit: string;
    minimum_stock: number;
}

/* ---------- Props ---------- */

interface Props {
    isStaff: boolean;
    // Staff props
    outletName?: string;
    todayRevenue?: number;
    todayTransactions?: number;
    todayItemsSold?: number;
    recentSales?: RecentSale[];
    outletInventory?: OutletInventoryItem[];
    // Pengelola props
    period?: string;
    periodLabel?: string;
    startDate?: string;
    endDate?: string;
    metrics?: Metrics;
    lowStock?: LowStock[];
    // recentSales reused but typed differently for pengelola
    recentPurchases?: RecentPurchase[];
    monthlyChart?: MonthlyChartPoint[];
    upcomingPayables?: UpcomingPayable[];
    outletId?: number | null;
    outlets?: OutletOption[];
}

const periodOptions = [
    { value: 'this_week', label: 'Minggu Ini' },
    { value: 'this_month', label: 'Bulan Ini' },
    { value: 'last_3_months', label: '3 Bulan' },
    { value: 'custom', label: 'Custom' },
];

export default function Dashboard(props: Props) {
    const {
        isStaff,
        // Staff
        outletName = '',
        todayRevenue = 0,
        todayTransactions = 0,
        todayItemsSold = 0,
        recentSales = [],
        outletInventory = [],
        // Pengelola
        period = 'this_month',
        periodLabel = '',
        startDate = '',
        endDate = '',
        metrics = { revenue: 0, cogs: 0, gross_profit: 0, gross_margin: 0, net_profit: 0 },
        lowStock = [],
        recentPurchases = [],
        monthlyChart = [],
        upcomingPayables = [],
        outletId = null,
        outlets = [],
    } = props;

    // ─── Staff View ─────────────────────────────────────────────
    if (isStaff) {
        return (
            <AppLayout title="Dashboard">
                <Head title="Dashboard" />

                <div className="mb-4">
                    <h2 className="text-lg font-semibold">{outletName}</h2>
                    <p className="text-sm text-muted-foreground">Penjualan hari ini</p>
                </div>

                {/* Today's Sales Summary */}
                <div className="grid gap-4 sm:grid-cols-3">
                    <StatCard
                        label="Omset Hari Ini"
                        value={formatRupiah(todayRevenue)}
                        icon={<Banknote className="h-5 w-5" />}
                    />
                    <StatCard
                        label="Total Transaksi"
                        value={todayTransactions.toString()}
                        icon={<ShoppingCart className="h-5 w-5" />}
                    />
                    <StatCard
                        label="Items Terjual"
                        value={todayItemsSold.toString()}
                        icon={<Package className="h-5 w-5" />}
                    />
                </div>

                {/* Recent Sales Today */}
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>Penjualan Hari Ini</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recentSales.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                Belum ada penjualan hari ini.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Produk</TableHead>
                                        <TableHead>Qty</TableHead>
                                        <TableHead>Revenue</TableHead>
                                        <TableHead>Waktu</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recentSales.map((s) => (
                                        <TableRow key={s.id}>
                                            <TableCell className="font-medium">
                                                {s.product ?? '-'}
                                            </TableCell>
                                            <TableCell>{s.quantity}</TableCell>
                                            <TableCell>{formatRupiah(s.revenue)}</TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {s.occurred_at
                                                    ? new Date(s.occurred_at).toLocaleTimeString('id-ID', {
                                                          hour: '2-digit',
                                                          minute: '2-digit',
                                                      })
                                                    : '-'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {/* Outlet Inventory */}
                <Card className="mt-6">
                    <CardHeader>
                        <CardTitle>Stok Outlet</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {outletInventory.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                Belum ada data inventory outlet.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Bahan</TableHead>
                                        <TableHead>Stok</TableHead>
                                        <TableHead>Min</TableHead>
                                        <TableHead>Satuan</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {outletInventory.map((item) => (
                                        <TableRow key={item.id}>
                                            <TableCell className="font-medium">
                                                {item.ingredient_name}
                                            </TableCell>
                                            <TableCell>
                                                {formatNumber(item.quantity, 2)}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {formatNumber(item.minimum_stock, 2)}
                                            </TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {item.base_unit}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                        <Link
                            href="/inventory"
                            className="mt-3 inline-block text-sm font-medium text-primary hover:underline"
                        >
                            Lihat detail inventory
                        </Link>
                    </CardContent>
                </Card>
            </AppLayout>
        );
    }

    // ─── Pengelola View (existing) ──────────────────────────────
    const maxChartValue = Math.max(
        1,
        ...monthlyChart.flatMap((point) => [point.revenue, point.expense]),
    );
    const [activePeriod, setActivePeriod] = useState(period);
    const [customStart, setCustomStart] = useState(startDate);
    const [customEnd, setCustomEnd] = useState(endDate);

    const queryParams = (extra: Record<string, string | number | undefined> = {}) => ({
        period: activePeriod,
        ...(outletId ? { outlet_id: outletId } : {}),
        ...extra,
    });

    const changePeriod = (newPeriod: string) => {
        setActivePeriod(newPeriod);
        if (newPeriod !== 'custom') {
            router.get('/dashboard', queryParams({ period: newPeriod }), {
                preserveState: true,
                preserveScroll: true,
            });
        }
    };

    const changeOutlet = (value: string) => {
        router.get(
            '/dashboard',
            {
                period: activePeriod,
                ...(activePeriod === 'custom'
                    ? { start_date: customStart, end_date: customEnd }
                    : {}),
                ...(value ? { outlet_id: value } : {}),
            },
            { preserveState: true, preserveScroll: true },
        );
    };

    const applyCustom = () => {
        router.get(
            '/dashboard',
            queryParams({ period: 'custom', start_date: customStart, end_date: customEnd }),
            { preserveState: true, preserveScroll: true },
        );
    };

    return (
        <AppLayout title="Dashboard">
            <Head title="Dashboard" />

            {/* Period Filter */}
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <Select
                    value={outletId ? String(outletId) : ''}
                    onChange={(e) => changeOutlet(e.target.value)}
                    className="w-44"
                >
                    <option value="">Semua outlet</option>
                    {outlets.map((o) => (
                        <option key={o.id} value={o.id}>
                            {o.name}
                        </option>
                    ))}
                </Select>
                <div className="flex gap-1 rounded-lg border border-border p-1">
                    {periodOptions.map((opt) => (
                        <button
                            key={opt.value}
                            type="button"
                            onClick={() => changePeriod(opt.value)}
                            className={cn(
                                'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                activePeriod === opt.value
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-muted',
                            )}
                        >
                            {opt.label}
                        </button>
                    ))}
                </div>

                {activePeriod === 'custom' && (
                    <div className="flex items-center gap-2">
                        <Input
                            type="date"
                            value={customStart}
                            onChange={(e) => setCustomStart(e.target.value)}
                            className="w-40"
                        />
                        <span className="text-muted-foreground">s/d</span>
                        <Input
                            type="date"
                            value={customEnd}
                            onChange={(e) => setCustomEnd(e.target.value)}
                            className="w-40"
                        />
                        <button
                            type="button"
                            onClick={applyCustom}
                            className="rounded-md bg-primary px-3 py-1.5 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                        >
                            Terapkan
                        </button>
                    </div>
                )}

                <span className="text-sm text-muted-foreground ml-auto">{periodLabel}</span>
            </div>

            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Omset"
                    value={formatRupiah(metrics.revenue)}
                    icon={<Banknote className="h-5 w-5" />}
                />
                <StatCard
                    label="COGS"
                    value={formatRupiah(metrics.cogs)}
                    accent="warning"
                    icon={<PackageMinus className="h-5 w-5" />}
                />
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
                    accent={metrics.net_profit < 0 ? 'danger' : 'success'}
                    icon={<Percent className="h-5 w-5" />}
                />
            </div>

            <Card className="mt-6">
                <CardHeader>
                    <CardTitle>Revenue vs Expense Bulanan</CardTitle>
                </CardHeader>
                <CardContent>
                    {monthlyChart.length === 0 ? (
                        <p className="py-6 text-center text-sm text-muted-foreground">
                            Belum ada data chart.
                        </p>
                    ) : (
                        <div className="space-y-4">
                            <div className="flex gap-4 text-xs text-muted-foreground">
                                <span className="flex items-center gap-2">
                                    <span className="h-2.5 w-2.5 rounded-full bg-primary" />
                                    Revenue
                                </span>
                                <span className="flex items-center gap-2">
                                    <span className="h-2.5 w-2.5 rounded-full bg-amber-500" />
                                    Expense
                                </span>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                                {monthlyChart.map((point) => (
                                    <div
                                        key={`${point.year}-${point.month}`}
                                        className="rounded-md border p-3"
                                    >
                                        <p className="text-sm font-medium">{point.label}</p>
                                        <div className="mt-3 space-y-2">
                                            <div>
                                                <div className="mb-1 flex justify-between text-xs text-muted-foreground">
                                                    <span>Revenue</span>
                                                    <span>{formatRupiah(point.revenue)}</span>
                                                </div>
                                                <div className="h-2 rounded-full bg-muted">
                                                    <div
                                                        className="h-2 rounded-full bg-primary"
                                                        style={{
                                                            width: `${Math.max(2, (point.revenue / maxChartValue) * 100)}%`,
                                                        }}
                                                    />
                                                </div>
                                            </div>
                                            <div>
                                                <div className="mb-1 flex justify-between text-xs text-muted-foreground">
                                                    <span>Expense</span>
                                                    <span>{formatRupiah(point.expense)}</span>
                                                </div>
                                                <div className="h-2 rounded-full bg-muted">
                                                    <div
                                                        className="h-2 rounded-full bg-amber-500"
                                                        style={{
                                                            width: `${Math.max(2, (point.expense / maxChartValue) * 100)}%`,
                                                        }}
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>
                    )}
                </CardContent>
            </Card>

            <div className="mt-6 grid gap-6 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Stok Perlu Perhatian</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {lowStock.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                Semua stok aman.
                            </p>
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
                        <Link
                            href="/inventory"
                            className="mt-3 inline-block text-sm font-medium text-primary hover:underline"
                        >
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
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                Belum ada penjualan.
                            </p>
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
                                            <TableCell className="font-medium">
                                                {s.product ?? '-'}
                                            </TableCell>
                                            <TableCell>{s.quantity}</TableCell>
                                            <TableCell>{formatRupiah(s.revenue)}</TableCell>
                                            <TableCell className="text-success">
                                                {formatRupiah((s as PengelolaRecentSale).profit)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                        <Link
                            href="/sales"
                            className="mt-3 inline-block text-sm font-medium text-primary hover:underline"
                        >
                            Lihat semua penjualan
                        </Link>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Pembelian Terbaru</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recentPurchases.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                Belum ada pembelian.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Tanggal</TableHead>
                                        <TableHead>Bahan</TableHead>
                                        <TableHead>Qty</TableHead>
                                        <TableHead>Total</TableHead>
                                        <TableHead>Source</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recentPurchases.map((p) => (
                                        <TableRow key={p.id}>
                                            <TableCell className="text-muted-foreground">
                                                {formatDate(p.occurred_at)}
                                            </TableCell>
                                            <TableCell className="font-medium">
                                                {p.ingredient ?? '-'}
                                            </TableCell>
                                            <TableCell>
                                                {formatNumber(p.quantity, 2)} {p.base_unit ?? ''}
                                            </TableCell>
                                            <TableCell>{formatRupiah(p.total)}</TableCell>
                                            <TableCell className="text-xs uppercase text-muted-foreground">
                                                {p.source}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                        <Link
                            href="/transactions"
                            className="mt-3 inline-block text-sm font-medium text-primary hover:underline"
                        >
                            Lihat semua pembelian
                        </Link>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Tagihan Jatuh Tempo</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {upcomingPayables.length === 0 ? (
                            <p className="py-6 text-center text-sm text-muted-foreground">
                                Tidak ada tagihan jatuh tempo.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Supplier</TableHead>
                                        <TableHead>Sisa</TableHead>
                                        <TableHead>Jatuh Tempo</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {upcomingPayables.map((p) => (
                                        <TableRow key={p.id}>
                                            <TableCell className="font-medium">
                                                {p.partner}
                                            </TableCell>
                                            <TableCell>{formatRupiah(p.remaining)}</TableCell>
                                            <TableCell className="text-muted-foreground">
                                                {formatDate(p.due_date)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                        <Link
                            href="/payables"
                            className="mt-3 inline-block text-sm font-medium text-primary hover:underline"
                        >
                            Lihat semua tagihan
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

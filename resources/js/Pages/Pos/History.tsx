import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, ArrowDownCircle, ArrowUpCircle } from 'lucide-react';
import PosLayout from '@/Layouts/PosLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { formatRupiah, formatDate } from '@/lib/format';

interface HistoryItem {
    id: number;
    type: 'purchase' | 'sale';
    name: string;
    detail: string;
    amount: number;
    occurred_at: string;
}

interface Props {
    history: HistoryItem[];
    filters: { from: string; to: string; type: string };
    outletName: string;
}

export default function PosHistory({ history, filters, outletName }: Props) {
    const applyFilter = (key: string, value: string) => {
        router.get(
            route('pos.history'),
            { ...filters, [key]: value },
            { preserveState: true, replace: true },
        );
    };

    return (
        <PosLayout>
            <Head title="Riwayat Transaksi" />

            <div className="mb-4">
                <Link
                    href={route('pos.landing')}
                    className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Kembali
                </Link>
            </div>

            <div className="mb-1">
                <h1 className="text-lg font-semibold">Riwayat Transaksi</h1>
                <p className="text-sm text-muted-foreground">{outletName}</p>
            </div>

            {/* Filter */}
            <div className="mb-4 flex flex-wrap items-center gap-3">
                <div className="flex gap-2">
                    {[
                        { key: 'all', label: 'Semua' },
                        { key: 'purchase', label: 'Pembelian' },
                        { key: 'sale', label: 'Penjualan' },
                    ].map((f) => (
                        <Button
                            key={f.key}
                            size="sm"
                            variant={filters.type === f.key ? 'default' : 'outline'}
                            onClick={() => applyFilter('type', f.key)}
                        >
                            {f.label}
                        </Button>
                    ))}
                </div>
                <div className="flex items-center gap-2 text-sm">
                    <Input
                        type="date"
                        value={filters.from}
                        onChange={(e) => applyFilter('from', e.target.value)}
                        className="w-auto"
                    />
                    <span className="text-muted-foreground">s/d</span>
                    <Input
                        type="date"
                        value={filters.to}
                        onChange={(e) => applyFilter('to', e.target.value)}
                        className="w-auto"
                    />
                </div>
            </div>

            {/* History list */}
            <div className="rounded-lg border border-border bg-card">
                {history.length === 0 ? (
                    <div className="py-12 text-center text-sm text-muted-foreground">
                        Belum ada transaksi.
                    </div>
                ) : (
                    <div className="divide-y">
                        {history.map((item) => (
                            <div key={`${item.type}-${item.id}`} className="flex items-center gap-3 px-4 py-3">
                                <div className={`shrink-0 rounded-full p-1.5 ${
                                    item.type === 'purchase'
                                        ? 'bg-blue-50 text-blue-600'
                                        : 'bg-green-50 text-green-600'
                                }`}>
                                    {item.type === 'purchase' ? (
                                        <ArrowDownCircle className="h-4 w-4" />
                                    ) : (
                                        <ArrowUpCircle className="h-4 w-4" />
                                    )}
                                </div>
                                <div className="min-w-0 flex-1">
                                    <div className="text-sm font-medium truncate">{item.name}</div>
                                    <div className="text-xs text-muted-foreground">
                                        {item.detail} · {formatDate(item.occurred_at)}
                                    </div>
                                </div>
                                <div className={`text-sm font-medium shrink-0 ${
                                    item.type === 'purchase' ? 'text-red-600' : 'text-green-600'
                                }`}>
                                    {item.type === 'purchase' ? '-' : '+'}{formatRupiah(item.amount)}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </PosLayout>
    );
}

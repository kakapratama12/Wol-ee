import { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ArrowUpCircle, ArrowDownCircle, ShoppingCart, Wrench, Filter } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { formatDate } from '@/lib/format';

interface Outlet {
    id: number;
    name: string;
    type: string;
}

interface Movement {
    id: number;
    ingredient: string | null;
    type: string;
    quantity: number;
    stock_after: number;
    reason: string | null;
    note: string | null;
    user: string | null;
    occurred_at: string | null;
}

interface Props {
    outlet: Outlet;
    movements: Movement[];
    filters: {
        start_date: string | null;
        end_date: string | null;
    };
}

const typeLabels: Record<string, string> = {
    purchase: 'Pembelian',
    adjustment: 'Adjustmen',
    usage: 'Pemakaian',
    production_input: 'Produksi',
    production_output: 'Hasil Produksi',
    waste: 'Waste',
};

const reasonLabels: Record<string, string> = {
    rusak: 'Rusak',
    expired: 'Expired',
    susut: 'Susut',
    lainnya: 'Lainnya',
};

function getTypeIcon(type: string) {
    switch (type) {
        case 'purchase':
            return <ShoppingCart className="h-4 w-4 text-green-600 dark:text-green-400" />;
        case 'adjustment':
            return <Wrench className="h-4 w-4 text-amber-600 dark:text-amber-400" />;
        case 'usage':
        case 'production_input':
            return <ArrowDownCircle className="h-4 w-4 text-red-600 dark:text-red-400" />;
        default:
            return <ArrowUpCircle className="h-4 w-4 text-blue-600 dark:text-blue-400" />;
    }
}

export default function StockMovements({ outlet, movements, filters }: Props) {
    const [startDate, setStartDate] = useState(filters.start_date ?? '');
    const [endDate, setEndDate] = useState(filters.end_date ?? '');

    const handleFilter = () => {
        router.get(route('outlets.stock.movements', outlet.id), {
            start_date: startDate || undefined,
            end_date: endDate || undefined,
        }, {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <AppLayout title={`Riwayat Stok - ${outlet.name}`}>
            <Head title={`Riwayat Stok - ${outlet.name}`} />

            <div className="space-y-6">
                {/* Back Button */}
                <div className="flex items-center gap-4">
                    <Link
                        href={route('outlets.inventory', outlet.id)}
                        className="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                    >
                        <ArrowLeft className="mr-1 h-4 w-4" />
                        Kembali
                    </Link>
                </div>

                {/* Header */}
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                        Riwayat Pergerakan Stok
                    </h1>
                    <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        {outlet.name}
                    </p>
                </div>

                {/* Filter */}
                <Card>
                    <CardContent className="p-4">
                        <div className="flex flex-wrap items-end gap-4">
                            <div className="flex-1 min-w-[150px]">
                                <Label htmlFor="start_date" className="text-xs">Dari</Label>
                                <Input
                                    id="start_date"
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                    className="mt-1"
                                />
                            </div>
                            <div className="flex-1 min-w-[150px]">
                                <Label htmlFor="end_date" className="text-xs">Sampai</Label>
                                <Input
                                    id="end_date"
                                    type="date"
                                    value={endDate}
                                    onChange={(e) => setEndDate(e.target.value)}
                                    className="mt-1"
                                />
                            </div>
                            <Button onClick={handleFilter} size="sm">
                                <Filter className="mr-1 h-4 w-4" />
                                Filter
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Movements List */}
                <Card>
                    <CardContent className="p-0">
                        {movements.length === 0 ? (
                            <div className="p-8 text-center text-gray-500 dark:text-gray-400">
                                Belum ada riwayat pergerakan stok.
                            </div>
                        ) : (
                            <div className="divide-y divide-gray-200 dark:divide-gray-700">
                                {movements.map((movement) => (
                                    <div
                                        key={movement.id}
                                        className="flex items-start justify-between p-4 hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                    >
                                        <div className="flex items-start gap-3">
                                            <div className="mt-0.5">
                                                {getTypeIcon(movement.type)}
                                            </div>
                                            <div>
                                                <p className="text-sm font-medium text-gray-900 dark:text-white">
                                                    {movement.ingredient}
                                                </p>
                                                <p className="text-xs text-gray-500 dark:text-gray-400">
                                                    {typeLabels[movement.type] ?? movement.type}
                                                    {movement.reason && (
                                                        <> · {reasonLabels[movement.reason] ?? movement.reason}</>
                                                    )}
                                                </p>
                                                {movement.note && (
                                                    <p className="mt-1 text-xs text-gray-400 dark:text-gray-500 italic">
                                                        "{movement.note}"
                                                    </p>
                                                )}
                                                <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                                    oleh {movement.user} · {movement.occurred_at ? formatDate(movement.occurred_at) : '-'}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="text-right">
                                            <p className={`text-sm font-semibold ${movement.quantity > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                                                {movement.quantity > 0 ? '+' : ''}{Number(movement.quantity).toLocaleString('id-ID')}
                                            </p>
                                            <p className="text-xs text-gray-400 dark:text-gray-500">
                                                Sisa: {Number(movement.stock_after).toLocaleString('id-ID')}
                                            </p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

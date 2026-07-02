import { Head, Link } from '@inertiajs/react';
import { Package, ShoppingCart, Wrench, History, ArrowLeft, AlertTriangle } from 'lucide-react';
import PosLayout from '@/Layouts/PosLayout';
import { Button } from '@/Components/ui/button';

interface Outlet {
    id: number;
    name: string;
}

interface Ingredient {
    id: number;
    name: string;
    unit: string;
    stock: number;
}

interface Props {
    outlet: Outlet;
    ingredients: Ingredient[];
}

export default function StockIndex({ outlet, ingredients }: Props) {
    const hasNegativeStock = ingredients.some((i) => i.stock < 0);

    return (
        <PosLayout title="Stok Outlet" branch={outlet.name} activeTab="stok">
            <Head title="Stok Outlet" />

            <div className="mx-auto max-w-2xl space-y-6">
                {/* Header */}
                <div>
                    <h2 className="text-lg font-semibold">Stok Outlet</h2>
                    <p className="text-sm text-muted-foreground">{outlet.name}</p>
                </div>

                {/* Negative stock warning */}
                {hasNegativeStock && (
                    <div className="flex items-center gap-3 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/30 dark:text-amber-300">
                        <AlertTriangle className="h-5 w-5 flex-shrink-0" />
                        <p>Beberapa stok outlet minus. Segera lakukan distribusi atau adjust.</p>
                    </div>
                )}

                {/* Quick Actions */}
                <div className="grid grid-cols-2 gap-4">
                    <Link href="/pos/stock/purchase">
                        <div className="flex flex-col items-center justify-center rounded-xl border border-border bg-card p-6 shadow-sm transition hover:border-primary hover:shadow-md">
                            <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-300">
                                <ShoppingCart className="h-6 w-6" />
                            </div>
                            <p className="text-sm font-semibold">Beli Bahan</p>
                            <p className="mt-1 text-xs text-muted-foreground text-center">Pembelian direct dari outlet</p>
                        </div>
                    </Link>

                    <Link href="/pos/stock/adjust">
                        <div className="flex flex-col items-center justify-center rounded-xl border border-border bg-card p-6 shadow-sm transition hover:border-primary hover:shadow-md">
                            <div className="mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-300">
                                <Wrench className="h-6 w-6" />
                            </div>
                            <p className="text-sm font-semibold">Adjust Stok</p>
                            <p className="mt-1 text-xs text-muted-foreground text-center">Catat susut, rusak, expired</p>
                        </div>
                    </Link>
                </div>

                {/* Riwayat */}
                <Link href="/pos/stock/movements">
                    <div className="flex items-center gap-4 rounded-xl border border-border bg-card p-4 shadow-sm transition hover:border-primary hover:shadow-md">
                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-300">
                            <History className="h-6 w-6" />
                        </div>
                        <div>
                            <p className="text-sm font-semibold">Riwayat Pergerakan Stok</p>
                            <p className="text-xs text-muted-foreground">Lihat semua pembelian & adjustmen</p>
                        </div>
                    </div>
                </Link>

                {/* Daftar Bahan dengan Stok Outlet */}
                <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
                    <h3 className="mb-4 text-sm font-semibold text-muted-foreground">Stok Outlet ({ingredients.length})</h3>
                    {ingredients.length === 0 ? (
                        <p className="text-sm text-muted-foreground">Belum ada stok di outlet ini. Minta distribusi dari gudang pusat.</p>
                    ) : (
                        <div className="space-y-2">
                            {ingredients.map((ingredient) => (
                                <div
                                    key={ingredient.id}
                                    className={`flex items-center justify-between rounded-lg border p-3 ${
                                        ingredient.stock < 0
                                            ? 'border-amber-300 bg-amber-50 dark:border-amber-700 dark:bg-amber-900/20'
                                            : 'border-border'
                                    }`}
                                >
                                    <div className="flex items-center gap-3">
                                        <Package className={`h-4 w-4 ${ingredient.stock < 0 ? 'text-amber-600' : 'text-muted-foreground'}`} />
                                        <span className="text-sm font-medium">{ingredient.name}</span>
                                    </div>
                                    <span className={`text-sm font-semibold ${
                                        ingredient.stock < 0 ? 'text-amber-600' : 'text-muted-foreground'
                                    }`}>
                                        {Math.round(ingredient.stock * 100) / 100} {ingredient.unit}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </PosLayout>
    );
}

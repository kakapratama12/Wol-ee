import { Head, Link } from '@inertiajs/react';
import { Package, ShoppingCart, Wrench, History, ArrowLeft } from 'lucide-react';
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
}

interface Props {
    outlet: Outlet;
    ingredients: Ingredient[];
}

export default function StockIndex({ outlet, ingredients }: Props) {
    return (
        <PosLayout title="Stok Outlet" branch={outlet.name}>
            <Head title="Stok Outlet" />

            <div className="mx-auto max-w-2xl space-y-6">
                {/* Header */}
                <div className="flex items-center gap-3">
                    <Link href="/pos">
                        <Button variant="ghost" size="icon" className="h-10 w-10">
                            <ArrowLeft className="h-5 w-5" />
                        </Button>
                    </Link>
                    <div>
                        <h2 className="text-lg font-semibold">Manajemen Stok</h2>
                        <p className="text-sm text-muted-foreground">{outlet.name}</p>
                    </div>
                </div>

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

                {/* Daftar Bahan */}
                <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
                    <h3 className="mb-4 text-sm font-semibold text-muted-foreground">Daftar Bahan ({ingredients.length})</h3>
                    {ingredients.length === 0 ? (
                        <p className="text-sm text-muted-foreground">Belum ada bahan.</p>
                    ) : (
                        <div className="space-y-2">
                            {ingredients.map((ingredient) => (
                                <div
                                    key={ingredient.id}
                                    className="flex items-center justify-between rounded-lg border border-border p-3"
                                >
                                    <div className="flex items-center gap-3">
                                        <Package className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm font-medium">{ingredient.name}</span>
                                    </div>
                                    <span className="text-xs text-muted-foreground">{ingredient.unit}</span>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </PosLayout>
    );
}

import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, ArrowUpCircle, ArrowDownCircle, ShoppingCart, Wrench } from 'lucide-react';
import PosLayout from '@/Layouts/PosLayout';
import { Button } from '@/Components/ui/button';

interface Outlet {
    id: number;
    name: string;
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
    outlet: Outlet | null;
    movements: Movement[];
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
            return <ShoppingCart className="h-4 w-4 text-green-600" />;
        case 'adjustment':
            return <Wrench className="h-4 w-4 text-amber-600" />;
        case 'usage':
        case 'production_input':
            return <ArrowDownCircle className="h-4 w-4 text-red-600" />;
        default:
            return <ArrowUpCircle className="h-4 w-4 text-blue-600" />;
    }
}

export default function Movements({ outlet, movements }: Props) {
    return (
        <PosLayout title="Riwayat Stok" branch={outlet?.name ?? 'Riwayat Stok'} activeTab="stok">
            <Head title="Riwayat Stok" />

            <div className="mx-auto max-w-2xl space-y-6">
                {/* Header */}
                <div className="flex items-center gap-3">
                    <Link href="/pos/stock">
                        <Button variant="ghost" size="icon" className="h-10 w-10">
                            <ArrowLeft className="h-5 w-5" />
                        </Button>
                    </Link>
                    <div>
                        <h2 className="text-lg font-semibold">Riwayat Pergerakan Stok</h2>
                        {outlet && <p className="text-sm text-muted-foreground">{outlet.name}</p>}
                    </div>
                </div>

                {/* Movements List */}
                <div className="rounded-xl border border-border bg-card p-6 shadow-sm">
                    {movements.length === 0 ? (
                        <div className="text-center py-8">
                            <p className="text-sm text-muted-foreground">Belum ada riwayat pergerakan stok.</p>
                        </div>
                    ) : (
                        <div className="space-y-3">
                            {movements.map((movement) => (
                                <div
                                    key={movement.id}
                                    className="flex items-start justify-between rounded-lg border border-border p-3"
                                >
                                    <div className="flex items-start gap-3">
                                        <div className="mt-0.5">
                                            {getTypeIcon(movement.type)}
                                        </div>
                                        <div>
                                            <p className="text-sm font-medium">{movement.ingredient}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {typeLabels[movement.type] ?? movement.type}
                                                {movement.reason && (
                                                    <> · {reasonLabels[movement.reason] ?? movement.reason}</>
                                                )}
                                            </p>
                                            {movement.note && (
                                                <p className="mt-1 text-xs text-muted-foreground italic">
                                                    "{movement.note}"
                                                </p>
                                            )}
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                oleh {movement.user} · {movement.occurred_at}
                                            </p>
                                        </div>
                                    </div>
                                    <div className="text-right">
                                        <p className={`text-sm font-semibold ${movement.quantity > 0 ? 'text-green-600' : 'text-red-600'}`}>
                                            {movement.quantity > 0 ? '+' : ''}{movement.quantity}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            Sisa: {movement.stock_after}
                                        </p>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </PosLayout>
    );
}

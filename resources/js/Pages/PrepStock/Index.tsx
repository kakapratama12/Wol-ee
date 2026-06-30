import { useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import { ChevronDown, ChevronUp, SlidersHorizontal, Plus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import StockStatusBadge from '@/Components/StockStatusBadge';
import Modal from '@/Components/ui/modal';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { formatNumber, formatDate } from '@/lib/format';

interface StockMovement {
    id: number;
    type: string;
    quantity: number;
    stock_after: number;
    note: string | null;
    occurred_at: string | null;
}

interface PrepItem {
    id: number;
    name: string;
    unit_type: string;
    base_unit: string;
    unit_price: number;
    current_stock: number;
    minimum_stock: number;
    status: string;
    stock_movements: StockMovement[];
}

interface Props {
    prepItems: PrepItem[];
    canManage: boolean;
}

const movementTypeLabels: Record<string, string> = {
    purchase: 'Pembelian',
    usage: 'Penggunaan',
    adjustment: 'Penyesuaian',
    reversal: 'Pembatalan',
    production_input: 'Bahan Produksi',
    production_output: 'Hasil Produksi',
    waste: 'Limbah/Expire',
};

export default function PrepStockIndex({ prepItems, canManage }: Props) {
    const [expandedItem, setExpandedItem] = useState<number | null>(null);
    const [adjusting, setAdjusting] = useState<PrepItem | null>(null);

    const adjustForm = useForm({ current_stock: '', note: '' });

    const toggleExpand = (itemId: number) => {
        setExpandedItem(expandedItem === itemId ? null : itemId);
    };

    const openAdjust = (item: PrepItem) => {
        setAdjusting(item);
        adjustForm.setData({ current_stock: String(item.current_stock), note: '' });
        adjustForm.clearErrors();
    };

    const submitAdjust = (e: React.FormEvent) => {
        e.preventDefault();
        if (!adjusting) return;
        adjustForm.post(`/prep-stocks/${adjusting.id}/adjust`, {
            onSuccess: () => setAdjusting(null),
        });
    };

    return (
        <AppLayout title="Stok Prep">
            <Head title="Stok Prep" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-headline">Stok Prep</h1>
                        <p className="text-sm text-muted-foreground">
                            {prepItems.length} bahan prep
                        </p>
                    </div>
                    <Link href="/production-runs">
                        <Button>
                            <Plus className="mr-2 h-4 w-4" />
                            Tambah Produksi
                        </Button>
                    </Link>
                </div>

                {prepItems.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center text-muted-foreground">
                            <p className="text-lg font-medium">Belum ada bahan prep</p>
                            <p className="mt-1 text-sm">
                                Tambah bahan dengan tipe <strong>Prep</strong> di halaman Inventory.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {prepItems.map((item) => (
                            <Card key={item.id}>
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between">
                                        <div className="space-y-1">
                                            <CardTitle className="flex items-center gap-2">
                                                {item.name}
                                                <StockStatusBadge status={item.status} />
                                            </CardTitle>
                                            <p className="text-sm text-muted-foreground">
                                                {item.unit_type} &middot; satuan: {item.base_unit}
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-headline">
                                                {formatNumber(item.current_stock, 2)}{' '}
                                                <span className="text-sm font-normal text-muted-foreground">
                                                    {item.base_unit}
                                                </span>
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Stok saat ini
                                            </p>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid grid-cols-2 gap-4 rounded-lg bg-muted/50 p-3 mb-3">
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Stok Minimum
                                            </p>
                                            <p className="font-semibold">
                                                {formatNumber(item.minimum_stock, 2)}{' '}
                                                {item.base_unit}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted-foreground">
                                                Total Riwayat
                                            </p>
                                            <p className="font-semibold">
                                                {item.stock_movements.length} transaksi
                                            </p>
                                        </div>
                                    </div>

                                    <div className="flex gap-2 mb-3">
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => openAdjust(item)}
                                        >
                                            <SlidersHorizontal className="h-4 w-4 mr-1" /> Sesuaikan
                                            Stok
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => toggleExpand(item.id)}
                                        >
                                            {expandedItem === item.id ? (
                                                <>
                                                    <ChevronUp className="h-4 w-4 mr-1" />{' '}
                                                    Sembunyikan Riwayat
                                                </>
                                            ) : (
                                                <>
                                                    <ChevronDown className="h-4 w-4 mr-1" /> Lihat
                                                    Riwayat Stok
                                                </>
                                            )}
                                        </Button>
                                    </div>

                                    {expandedItem === item.id && (
                                        <div className="border-t pt-3">
                                            <h4 className="text-sm font-medium mb-2">
                                                Riwayat Pergerakan Stok
                                            </h4>
                                            {item.stock_movements.length === 0 ? (
                                                <p className="text-sm text-muted-foreground">
                                                    Belum ada riwayat pergerakan stok.
                                                </p>
                                            ) : (
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead>Tanggal</TableHead>
                                                            <TableHead>Tipe</TableHead>
                                                            <TableHead className="text-right">
                                                                Jumlah
                                                            </TableHead>
                                                            <TableHead className="text-right">
                                                                Stok Setelah
                                                            </TableHead>
                                                            <TableHead>Catatan</TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {item.stock_movements.map((movement) => (
                                                            <TableRow key={movement.id}>
                                                                <TableCell>
                                                                    {formatDate(
                                                                        movement.occurred_at,
                                                                    )}
                                                                </TableCell>
                                                                <TableCell>
                                                                    <span className="text-xs capitalize">
                                                                        {movementTypeLabels[
                                                                            movement.type
                                                                        ] ?? movement.type}
                                                                    </span>
                                                                </TableCell>
                                                                <TableCell className="text-right font-medium">
                                                                    <span
                                                                        className={
                                                                            movement.quantity >= 0
                                                                                ? 'text-green-600'
                                                                                : 'text-red-600'
                                                                        }
                                                                    >
                                                                        {movement.quantity >= 0
                                                                            ? '+'
                                                                            : ''}
                                                                        {formatNumber(
                                                                            movement.quantity,
                                                                            2,
                                                                        )}
                                                                    </span>
                                                                </TableCell>
                                                                <TableCell className="text-right">
                                                                    {formatNumber(
                                                                        movement.stock_after,
                                                                        2,
                                                                    )}
                                                                </TableCell>
                                                                <TableCell className="text-muted-foreground text-sm">
                                                                    {movement.note || '-'}
                                                                </TableCell>
                                                            </TableRow>
                                                        ))}
                                                    </TableBody>
                                                </Table>
                                            )}
                                        </div>
                                    )}
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}
            </div>

            {/* Adjust Stock Modal */}
            <Modal
                open={!!adjusting}
                onClose={() => setAdjusting(null)}
                title={`Sesuaikan Stok - ${adjusting?.name ?? ''}`}
            >
                <form onSubmit={submitAdjust} className="space-y-4">
                    <div>
                        <Label htmlFor="adjust_stock">Stok aktual ({adjusting?.base_unit})</Label>
                        <Input
                            id="adjust_stock"
                            type="number"
                            step="0.0001"
                            value={adjustForm.data.current_stock}
                            onChange={(e) => adjustForm.setData('current_stock', e.target.value)}
                        />
                        {adjustForm.errors.current_stock && (
                            <p className="mt-1 text-xs text-destructive">
                                {adjustForm.errors.current_stock}
                            </p>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="adjust_note">Catatan (opsional)</Label>
                        <Input
                            id="adjust_note"
                            value={adjustForm.data.note}
                            onChange={(e) => adjustForm.setData('note', e.target.value)}
                        />
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => setAdjusting(null)}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={adjustForm.processing}>
                            Simpan
                        </Button>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}

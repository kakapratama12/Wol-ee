import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { Package, ChevronDown, ChevronUp, Edit2, Factory } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import Modal from '@/Components/ui/modal';
import { formatRupiah, formatNumber, formatDate, formatPercent } from '@/lib/format';

interface ProductionDetail {
    id: number;
    produced_at: string | null;
    yield_actual: number;
    waste_count: number;
    total_cost: number;
    cost_per_unit: number;
    notes: string | null;
}

interface BatchProduct {
    id: number;
    name: string;
    unit: string;
    selling_price: number;
    current_stock: number;
    avg_cogs: number;
    margin: number;
    production_count: number;
    production_details: ProductionDetail[];
}

interface Props {
    batchProducts: BatchProduct[];
}

export default function FinishedGoodsIndex({ batchProducts }: Props) {
    const [expandedProduct, setExpandedProduct] = useState<number | null>(null);
    const [adjustingProduct, setAdjustingProduct] = useState<BatchProduct | null>(null);

    const adjustForm = useForm({
        adjustment: '',
        note: '',
    });

    const toggleExpand = (productId: number) => {
        setExpandedProduct(expandedProduct === productId ? null : productId);
    };

    const openAdjust = (product: BatchProduct) => {
        setAdjustingProduct(product);
        adjustForm.setData({ adjustment: '', note: '' });
        adjustForm.clearErrors();
    };

    const submitAdjust = (e: React.FormEvent) => {
        e.preventDefault();
        if (!adjustingProduct) return;
        adjustForm.post(`/finished-goods/${adjustingProduct.id}/adjust`, {
            onSuccess: () => setAdjustingProduct(null),
        });
    };

    return (
        <AppLayout title="Stok Produk Jadi">
            <Head title="Stok Produk Jadi" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Stok Produk Jadi</h1>
                        <p className="text-sm text-muted-foreground">
                            {batchProducts.length} produk batch
                        </p>
                    </div>
                </div>

                {batchProducts.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center text-muted-foreground">
                            <Package className="mx-auto mb-4 h-12 w-12 opacity-30" />
                            <p className="text-lg font-medium">Belum ada produk batch</p>
                            <p className="mt-1 text-sm">
                                Buat produk dengan tipe <strong>Batch</strong> di halaman Produk & Resep, lalu produksi untuk mengisi stok.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {batchProducts.map((product) => (
                            <Card key={product.id}>
                                <CardHeader className="pb-3">
                                    <div className="flex items-start justify-between">
                                        <div className="space-y-1">
                                            <CardTitle className="flex items-center gap-2">
                                                {product.name}
                                                {product.current_stock < 0 && (
                                                    <Badge variant="destructive" className="text-xs">
                                                        Stok Minus
                                                    </Badge>
                                                )}
                                            </CardTitle>
                                            <p className="text-sm text-muted-foreground">
                                                {product.production_count} produksi tercatat
                                            </p>
                                        </div>
                                        <div className="text-right">
                                            <p className="text-2xl font-bold">
                                                {formatNumber(product.current_stock)} <span className="text-sm font-normal text-muted-foreground">{product.unit}</span>
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Stok saat ini
                                            </p>
                                        </div>
                                    </div>
                                </CardHeader>
                                <CardContent>
                                    <div className="grid grid-cols-3 gap-4 rounded-lg bg-muted/50 p-3 mb-3">
                                        <div>
                                            <p className="text-xs text-muted-foreground">Harga Jual</p>
                                            <p className="font-semibold">{formatRupiah(product.selling_price)}</p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted-foreground">COGS Rata-rata</p>
                                            <p className="font-semibold">{formatRupiah(product.avg_cogs)}</p>
                                        </div>
                                        <div>
                                            <p className="text-xs text-muted-foreground">Margin</p>
                                            <p className="font-semibold text-success">{formatPercent(product.margin)}</p>
                                        </div>
                                    </div>

                                    <div className="flex gap-2 mb-3">
                                        <Button variant="outline" size="sm" onClick={() => openAdjust(product)}>
                                            <Edit2 className="h-4 w-4 mr-1" /> Adjust Stok
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => toggleExpand(product.id)}
                                        >
                                            {expandedProduct === product.id ? (
                                                <>
                                                    <ChevronUp className="h-4 w-4 mr-1" /> Sembunyikan Detail
                                                </>
                                            ) : (
                                                <>
                                                    <ChevronDown className="h-4 w-4 mr-1" /> Lihat Detail Produksi
                                                </>
                                            )}
                                        </Button>
                                    </div>

                                    {expandedProduct === product.id && (
                                        <div className="border-t pt-3">
                                            <h4 className="text-sm font-medium mb-2">Riwayat Produksi</h4>
                                            {product.production_details.length === 0 ? (
                                                <p className="text-sm text-muted-foreground">Belum ada produksi.</p>
                                            ) : (
                                                <Table>
                                                    <TableHeader>
                                                        <TableRow>
                                                            <TableHead>Tanggal</TableHead>
                                                            <TableHead className="text-center">Yield</TableHead>
                                                            <TableHead className="text-center">Waste</TableHead>
                                                            <TableHead className="text-right">Total Biaya</TableHead>
                                                            <TableHead className="text-right">Biaya/Unit</TableHead>
                                                            <TableHead>Catatan</TableHead>
                                                        </TableRow>
                                                    </TableHeader>
                                                    <TableBody>
                                                        {product.production_details.map((detail) => (
                                                            <TableRow key={detail.id}>
                                                                <TableCell>{formatDate(detail.produced_at)}</TableCell>
                                                                <TableCell className="text-center font-medium">
                                                                    {formatNumber(detail.yield_actual)}
                                                                </TableCell>
                                                                <TableCell className="text-center">
                                                                    {detail.waste_count > 0 ? (
                                                                        <span className="text-orange-600">
                                                                            {formatNumber(detail.waste_count)}
                                                                        </span>
                                                                    ) : (
                                                                        <span className="text-muted-foreground">0</span>
                                                                    )}
                                                                </TableCell>
                                                                <TableCell className="text-right">
                                                                    {formatRupiah(detail.total_cost)}
                                                                </TableCell>
                                                                <TableCell className="text-right">
                                                                    {formatRupiah(detail.cost_per_unit)}
                                                                </TableCell>
                                                                <TableCell className="text-muted-foreground text-sm">
                                                                    {detail.notes || '-'}
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
                open={!!adjustingProduct}
                onClose={() => setAdjustingProduct(null)}
                title={`Adjust Stok - ${adjustingProduct?.name ?? ''}`}
            >
                <form onSubmit={submitAdjust} className="space-y-4">
                    <div className="rounded-lg bg-muted/50 p-3 text-sm text-muted-foreground">
                        <p>Stok saat ini: <strong>{formatNumber(adjustingProduct?.current_stock ?? 0)} {adjustingProduct?.unit}</strong></p>
                    </div>
                    <div>
                        <Label>Penyesuaian (pcs)</Label>
                        <Input
                            type="number"
                            placeholder=" positif = tambah, negatif = kurang"
                            value={adjustForm.data.adjustment}
                            onChange={(e) => adjustForm.setData('adjustment', e.target.value)}
                        />
                        <p className="mt-1 text-xs text-muted-foreground">
                            Contoh: 10 untuk menambah stok, -5 untuk mengurangi stok.
                        </p>
                        {adjustForm.errors.adjustment && (
                            <p className="mt-1 text-xs text-destructive">{adjustForm.errors.adjustment}</p>
                        )}
                    </div>
                    <div>
                        <Label>Catatan (opsional)</Label>
                        <Input
                            placeholder="Misal: Koreksi stok fisik"
                            value={adjustForm.data.note}
                            onChange={(e) => adjustForm.setData('note', e.target.value)}
                        />
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={() => setAdjustingProduct(null)}>
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

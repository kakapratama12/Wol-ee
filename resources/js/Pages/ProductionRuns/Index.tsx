import { useState, useMemo } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { Trash2, Plus, Factory, AlertTriangle, Pencil } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select } from '@/Components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import Modal from '@/Components/ui/modal';
import { formatRupiah, formatNumber, formatDate } from '@/lib/format';

interface RecipeItem {
    ingredient_id: number;
    ingredient: string | null;
    base_unit: string | null;
    quantity: number;
    unit_price: number;
}

interface BatchProduct {
    id: number;
    name: string;
    unit: string;
    estimated_yield_per_batch: number | null;
    recipe: RecipeItem[];
}

interface ProductionRun {
    id: number;
    product: string | null;
    product_id: number;
    batch_count: number;
    yield_actual: number;
    waste_count: number;
    yield_recorded: boolean;
    total_cost: number;
    cost_per_unit: number;
    yield_per_batch: number;
    waste_percentage: number;
    notes: string | null;
    produced_at: string | null;
}

interface Props {
    runs: ProductionRun[];
    batchProducts: BatchProduct[];
}

interface ItemRow {
    ingredient_id: number;
    ingredient: string;
    base_unit: string;
    quantity: string;
    unit_price: number;
}

export default function ProductionRunsIndex({ runs, batchProducts }: Props) {
    const [showForm, setShowForm] = useState(false);
    const [editingRun, setEditingRun] = useState<ProductionRun | null>(null);

    const form = useForm({
        product_id: '',
        batch_count: '1',
        items: [] as ItemRow[],
        notes: '',
    });

    const yieldForm = useForm({
        yield_actual: '',
        waste_count: '0',
    });

    const selectProduct = (productId: string) => {
        const product = batchProducts.find((p) => String(p.id) === productId);
        if (!product) return;

        form.setData({
            product_id: productId,
            batch_count: '1',
            items: product.recipe.map((r) => ({
                ingredient_id: r.ingredient_id,
                ingredient: r.ingredient ?? '-',
                base_unit: r.base_unit ?? '-',
                quantity: String(r.quantity),
                unit_price: r.unit_price,
            })),
            notes: '',
        });
    };

    const updateItem = (index: number, key: 'quantity', value: string) => {
        const items = [...form.data.items];
        items[index] = { ...items[index], [key]: value };
        form.setData('items', items);
    };

    const estimatedTotalCost = useMemo(() => {
        return form.data.items.reduce((sum, item) => {
            return sum + (parseFloat(item.quantity) || 0) * item.unit_price;
        }, 0);
    }, [form.data.items]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            product_id: Number(data.product_id),
            batch_count: Number(data.batch_count),
            items: data.items.map((item) => ({
                ingredient_id: item.ingredient_id,
                quantity_used: Number(item.quantity),
            })),
            notes: data.notes || null,
        }));
        form.post('/production-runs', {
            onSuccess: () => {
                setShowForm(false);
                form.reset();
            },
        });
    };

    const reverseRun = (run: ProductionRun) => {
        if (confirm(`Batalkan produksi "${run.product}" (${run.yield_actual} ${run.product}? Seluruh stok akan dikembalikan.`)) {
            router.delete(`/production-runs/${run.id}`);
        }
    };

    const openEditYield = (run: ProductionRun) => {
        setEditingRun(run);
        yieldForm.setData({
            yield_actual: String(run.yield_actual),
            waste_count: String(run.waste_count),
        });
        yieldForm.clearErrors();
    };

    const submitYield = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingRun) return;
        yieldForm.put(`/production-runs/${editingRun.id}/yield`, {
            onSuccess: () => setEditingRun(null),
        });
    };

    return (
        <AppLayout>
            <Head title="Produksi" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Produksi</h1>
                    {batchProducts.length > 0 && (
                        <Button onClick={() => { setShowForm(true); form.reset(); form.setData('items', []); }}>
                            <Plus className="mr-2 h-4 w-4" />
                            Produksi Baru
                        </Button>
                    )}
                </div>

                {batchProducts.length === 0 && (
                    <Card>
                        <CardContent className="py-12 text-center text-muted-foreground">
                            <Factory className="mx-auto mb-4 h-12 w-12 opacity-30" />
                            <p className="text-lg font-medium">Belum ada produk batch</p>
                            <p className="mt-1 text-sm">
                                Buat produk dengan tipe <strong>Batch</strong> di halaman Produk & Resep terlebih dulu.
                            </p>
                        </CardContent>
                    </Card>
                )}

                {/* Create Form */}
                {showForm && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Produksi Baru</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={submit} className="space-y-4">
                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <Label>Resep / Produk</Label>
                                        <select
                                            className="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                            value={form.data.product_id}
                                            onChange={(e) => selectProduct(e.target.value)}
                                        >
                                            <option value="">Pilih produk...</option>
                                            {batchProducts.map((p) => (
                                                <option key={p.id} value={p.id}>{p.name}</option>
                                            ))}
                                        </select>
                                    </div>
                                    <div>
                                        <Label>Jumlah Batch</Label>
                                        <Input
                                            type="number"
                                            min="1"
                                            className="mt-1"
                                            value={form.data.batch_count}
                                            onChange={(e) => form.setData('batch_count', e.target.value)}
                                        />
                                    </div>
                                </div>

                                {/* Ingredient Table */}
                                {form.data.items.length > 0 && (
                                    <div>
                                        <Label>Bahan Terpakai (bisa diedit)</Label>
                                        <Table className="mt-2">
                                            <TableHeader>
                                                <TableRow>
                                                    <TableHead>Bahan</TableHead>
                                                    <TableHead className="w-32">Qty</TableHead>
                                                    <TableHead>Satuan</TableHead>
                                                    <TableHead className="text-right">Harga/Unit</TableHead>
                                                    <TableHead className="text-right">Subtotal</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {form.data.items.map((item, i) => (
                                                    <TableRow key={item.ingredient_id}>
                                                        <TableCell>{item.ingredient}</TableCell>
                                                        <TableCell>
                                                            <Input
                                                                type="number"
                                                                step="0.01"
                                                                min="0"
                                                                value={item.quantity}
                                                                onChange={(e) => updateItem(i, 'quantity', e.target.value)}
                                                            />
                                                        </TableCell>
                                                        <TableCell>{item.base_unit}</TableCell>
                                                        <TableCell className="text-right">{formatRupiah(item.unit_price)}</TableCell>
                                                        <TableCell className="text-right">
                                                            {formatRupiah((parseFloat(item.quantity) || 0) * item.unit_price)}
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                                <TableRow className="font-semibold">
                                                    <TableCell colSpan={4} className="text-right">Total Biaya Bahan</TableCell>
                                                    <TableCell className="text-right">{formatRupiah(estimatedTotalCost)}</TableCell>
                                                </TableRow>
                                            </TableBody>
                                        </Table>
                                    </div>
                                )}

                                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    <div>
                                        <Label>Catatan</Label>
                                        <Input
                                            className="mt-1"
                                            placeholder="Opsional"
                                            value={form.data.notes}
                                            onChange={(e) => form.setData('notes', e.target.value)}
                                        />
                                    </div>
                                </div>

                                {form.errors.batch_count && (
                                    <div className="flex items-center gap-2 rounded-md bg-red-50 p-3 text-sm text-red-700">
                                        <AlertTriangle className="h-4 w-4" />
                                        {form.errors.batch_count}
                                    </div>
                                )}

                                <div className="flex gap-2">
                                    <Button type="submit" disabled={form.processing}>
                                        {form.processing ? 'Menyimpan...' : 'Simpan Produksi'}
                                    </Button>
                                    <Button type="button" variant="outline" onClick={() => setShowForm(false)}>
                                        Batal
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {/* List */}
                {runs.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Riwayat Produksi</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Tanggal</TableHead>
                                        <TableHead>Produk</TableHead>
                                        <TableHead className="text-center">Batch</TableHead>
                                        <TableHead className="text-center">Yield</TableHead>
                                        <TableHead className="text-center">Waste</TableHead>
                                        <TableHead className="text-right">Total Biaya</TableHead>
                                        <TableHead className="text-right">Biaya/Unit</TableHead>
                                        <TableHead></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {runs.map((run) => (
                                        <TableRow key={run.id}>
                                            <TableCell>{formatDate(run.produced_at)}</TableCell>
                                            <TableCell className="font-medium">{run.product}</TableCell>
                                            <TableCell className="text-center">{run.batch_count}</TableCell>
                                            <TableCell className="text-center font-medium">
                                                {run.yield_recorded ? (
                                                    formatNumber(run.yield_actual)
                                                ) : (
                                                    <span className="text-muted-foreground italic text-xs">Belum Catat</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-center">
                                                {run.waste_count > 0 ? (
                                                    <span className="text-orange-600">{formatNumber(run.waste_count)} ({run.waste_percentage}%)</span>
                                                ) : (
                                                    <span className="text-muted-foreground">0</span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right">{formatRupiah(run.total_cost)}</TableCell>
                                            <TableCell className="text-right">{formatRupiah(run.cost_per_unit)}</TableCell>
                                            <TableCell>
                                                <div className="flex gap-1">
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => openEditYield(run)}
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => reverseRun(run)}
                                                        className="text-red-600 hover:text-red-700"
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Edit Yield Modal */}
            <Modal
                open={!!editingRun}
                onClose={() => setEditingRun(null)}
                title={`Edit Yield - ${editingRun?.product ?? ''}`}
            >
                <form onSubmit={submitYield} className="space-y-4">
                    <div className="rounded-lg bg-muted/50 p-3 text-sm text-muted-foreground">
                        <p>Production Run #{editingRun?.id}</p>
                        <p>Batch: {editingRun?.batch_count} | Total Biaya: {formatRupiah(editingRun?.total_cost ?? 0)}</p>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label>Yield Aktual (pcs)</Label>
                            <Input
                                type="number"
                                min="1"
                                value={yieldForm.data.yield_actual}
                                onChange={(e) => yieldForm.setData('yield_actual', e.target.value)}
                            />
                            {yieldForm.errors.yield_actual && (
                                <p className="mt-1 text-xs text-destructive">{yieldForm.errors.yield_actual}</p>
                            )}
                        </div>
                        <div>
                            <Label>Waste (pcs)</Label>
                            <Input
                                type="number"
                                min="0"
                                value={yieldForm.data.waste_count}
                                onChange={(e) => yieldForm.setData('waste_count', e.target.value)}
                            />
                        </div>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Stok produk jadi akan otomatis disesuaikan berdasarkan perubahan yield.
                    </p>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={() => setEditingRun(null)}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={yieldForm.processing}>
                            Simpan
                        </Button>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}

import { useState, useMemo, useEffect } from 'react';
import { Head, useForm, router, usePage } from '@inertiajs/react';
import { Trash2, Plus, Factory, AlertTriangle, Pencil, FlaskConical } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
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

interface RunItem {
    id: number;
    ingredient_id: number;
    ingredient: string;
    base_unit: string;
    quantity_used: number;
    unit_cost_snapshot: number;
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
    items: RunItem[];
}

interface Ingredient {
    id: number;
    name: string;
    base_unit: string;
    unit_price: number;
    current_stock: number;
}

interface Props {
    runs: ProductionRun[];
    batchProducts: BatchProduct[];
    ingredients: Ingredient[];
}

interface EditItemRow {
    ingredient_id: number;
    ingredient: string;
    base_unit: string;
    quantity_used: string;
    unit_price: number;
}

export default function ProductionRunsIndex({ runs, batchProducts, ingredients }: Props) {
    const { url } = usePage();
    const [showForm, setShowForm] = useState(false);
    const [editingRun, setEditingRun] = useState<ProductionRun | null>(null);
    const [editingItemsRun, setEditingItemsRun] = useState<ProductionRun | null>(null);

    const form = useForm({
        product_id: '',
        batch_count: '1',
        notes: '',
    });

    // Auto-open form when navigated with ?produce=<product_id>
    useEffect(() => {
        const params = new URLSearchParams(url.split('?')[1] || '');
        const produceId = params.get('produce');
        if (produceId && batchProducts.some((p) => p.id === Number(produceId))) {
            setShowForm(true);
            form.setData('product_id', produceId);
        }
    }, []);

    const yieldForm = useForm({
        yield_actual: '',
        waste_count: '0',
    });

    const itemsForm = useForm({
        items: [] as EditItemRow[],
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            product_id: Number(data.product_id),
            batch_count: Number(data.batch_count),
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

    const openEditItems = (run: ProductionRun) => {
        setEditingItemsRun(run);
        itemsForm.setData({
            items: run.items.map((item) => ({
                ingredient_id: item.ingredient_id,
                ingredient: item.ingredient,
                base_unit: item.base_unit,
                quantity_used: String(item.quantity_used),
                unit_price: item.unit_cost_snapshot,
            })),
        });
        itemsForm.clearErrors();
    };

    const updateEditItem = (index: number, value: string) => {
        const items = [...itemsForm.data.items];
        items[index] = { ...items[index], quantity_used: value };
        itemsForm.setData('items', items);
    };

    const removeEditItem = (index: number) => {
        itemsForm.setData('items', itemsForm.data.items.filter((_, idx) => idx !== index));
    };

    const [addIngredientId, setAddIngredientId] = useState<string>('');

    const addExtraIngredient = () => {
        if (!addIngredientId) return;
        const ing = ingredients.find((i) => i.id === Number(addIngredientId));
        if (!ing) return;
        // Check if already in list
        if (itemsForm.data.items.some((item) => item.ingredient_id === ing.id)) return;
        itemsForm.setData('items', [
            ...itemsForm.data.items,
            {
                ingredient_id: ing.id,
                ingredient: ing.name,
                base_unit: ing.base_unit,
                quantity_used: '1',
                unit_price: ing.unit_price,
            },
        ]);
        setAddIngredientId('');
    };

    const editItemsTotalCost = useMemo(() => {
        return itemsForm.data.items.reduce((sum, item) => {
            return sum + (parseFloat(item.quantity_used) || 0) * item.unit_price;
        }, 0);
    }, [itemsForm.data.items]);

    const submitItems = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingItemsRun) return;
        itemsForm.transform((data) => ({
            items: data.items.map((item) => ({
                ingredient_id: item.ingredient_id,
                quantity_used: Number(item.quantity_used),
            })),
        }));
        itemsForm.put(`/production-runs/${editingItemsRun.id}/items`, {
            onSuccess: () => setEditingItemsRun(null),
        });
    };

    return (
        <AppLayout>
            <Head title="Produksi" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-headline">Produksi</h1>
                    {batchProducts.length > 0 && (
                        <Button onClick={() => { setShowForm(true); form.reset(); }}>
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

                {/* Create Form — simplified: just product + batch count + notes */}
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
                                            onChange={(e) => form.setData('product_id', e.target.value)}
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

                                <div>
                                    <Label>Catatan</Label>
                                    <Input
                                        className="mt-1"
                                        placeholder="Opsional"
                                        value={form.data.notes}
                                        onChange={(e) => form.setData('notes', e.target.value)}
                                    />
                                </div>

                                <p className="text-xs text-muted-foreground">
                                    Bahan akan otomatis digunakan sesuai resep × jumlah batch. Stok bisa diedit setelah produksi tercatat.
                                </p>

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

                {/* History Table */}
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
                                                        onClick={() => openEditItems(run)}
                                                        title="Edit Bahan"
                                                    >
                                                        <FlaskConical className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => openEditYield(run)}
                                                        title="Edit Yield"
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

            {/* Edit Ingredients Modal */}
            <Modal
                open={!!editingItemsRun}
                onClose={() => setEditingItemsRun(null)}
                title={`Edit Bahan - ${editingItemsRun?.product ?? ''}`}
            >
                <form onSubmit={submitItems} className="space-y-4">
                    <div className="rounded-lg bg-muted/50 p-3 text-sm text-muted-foreground">
                        <p>Production Run #{editingItemsRun?.id}</p>
                        <p>Batch: {editingItemsRun?.batch_count}</p>
                    </div>

                    <div className="space-y-3">
                        {itemsForm.data.items.map((item, i) => (
                            <div key={item.ingredient_id} className="rounded-lg border p-3">
                                <div className="flex items-center justify-between mb-2">
                                    <p className="font-medium text-sm">{item.ingredient}</p>
                                    <div className="flex items-center gap-2">
                                        <p className="text-xs text-muted-foreground">{formatRupiah(item.unit_price)}/{item.base_unit}</p>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="h-6 w-6"
                                            onClick={() => removeEditItem(i)}
                                        >
                                            <Trash2 className="h-3 w-3 text-destructive" />
                                        </Button>
                                    </div>
                                </div>
                                <div className="flex items-end gap-2">
                                    <div className="flex-1">
                                        <Label className="text-xs text-muted-foreground">Qty</Label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            value={item.quantity_used}
                                            onChange={(e) => updateEditItem(i, e.target.value)}
                                            className="mt-1"
                                        />
                                    </div>
                                    <p className="pb-2 text-sm font-medium whitespace-nowrap">
                                        = {formatRupiah((parseFloat(item.quantity_used) || 0) * item.unit_price)}
                                    </p>
                                </div>
                                <p className="text-xs text-muted-foreground mt-1">{item.base_unit}</p>
                            </div>
                        ))}

                    {/* Add Extra Ingredient */}
                    <div className="flex items-end gap-2 rounded-lg border border-dashed p-3">
                        <div className="flex-1">
                            <Label className="text-xs text-muted-foreground">Tambah Bahan</Label>
                            <select
                                className="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                value={addIngredientId}
                                onChange={(e) => setAddIngredientId(e.target.value)}
                            >
                                <option value="">Pilih bahan dari inventory...</option>
                                {ingredients
                                    .filter((ing) => !itemsForm.data.items.some((item) => item.ingredient_id === ing.id))
                                    .map((ing) => (
                                        <option key={ing.id} value={ing.id}>
                                            {ing.name} ({ing.base_unit}) — Stok: {formatNumber(ing.current_stock)}
                                        </option>
                                    ))}
                            </select>
                        </div>
                        <Button type="button" variant="outline" size="sm" onClick={addExtraIngredient} disabled={!addIngredientId}>
                            <Plus className="mr-1 h-4 w-4" />
                            Tambah
                        </Button>
                    </div>
                    </div>

                    <div className="flex justify-end rounded-lg bg-muted/50 p-3">
                        <p className="text-sm font-semibold">Total: {formatRupiah(editItemsTotalCost)}</p>
                    </div>

                    {itemsForm.errors.items && (
                        <div className="flex items-center gap-2 rounded-md bg-red-50 p-3 text-sm text-red-700">
                            <AlertTriangle className="h-4 w-4" />
                            {itemsForm.errors.items}
                        </div>
                    )}

                    <p className="text-xs text-muted-foreground">
                        Perubahan jumlah bahan akan otomatis menyesuaikan stok dan total biaya.
                    </p>

                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={() => setEditingItemsRun(null)}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={itemsForm.processing}>
                            {itemsForm.processing ? 'Menyimpan...' : 'Simpan'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}

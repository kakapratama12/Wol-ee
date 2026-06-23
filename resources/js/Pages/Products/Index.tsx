import { useMemo, useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { Plus, Pencil, Trash2, Utensils, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/ui/modal';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select } from '@/Components/ui/select';
import { Badge } from '@/Components/ui/badge';
import { formatRupiah, formatPercent } from '@/lib/format';

interface RecipeRow {
    ingredient_id: number;
    ingredient: string | null;
    base_unit: string | null;
    unit_price: number;
    quantity: number;
    cost: number;
}

interface Product {
    id: number;
    name: string;
    unit: string;
    selling_price: number;
    recipe_type: 'unit' | 'batch';
    estimated_yield_per_batch: number | null;
    is_active: boolean;
    is_prep: boolean;
    cogs: number;
    margin: number;
    recipe: RecipeRow[];
}

interface IngredientOption {
    id: number;
    name: string;
    base_unit: string;
    unit_price: number;
    item_type: string;
}

interface Props {
    products: Product[];
    ingredients: IngredientOption[];
}

interface EditableRow {
    ingredient_id: string;
    quantity: string;
}

export default function ProductsIndex({ products, ingredients }: Props) {
    // Group ingredients by item_type for the recipe dropdown
    const ingredientGroups = useMemo(() => {
        const groups: Record<string, { label: string; items: IngredientOption[] }> = {
            raw_material: { label: 'Bahan Dasar', items: [] },
            prep: { label: 'Prep', items: [] },
        };
        ingredients.forEach((ing) => {
            if (groups[ing.item_type]) {
                groups[ing.item_type].items.push(ing);
            }
        });
        return groups;
    }, [ingredients]);

    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Product | null>(null);
    const [recipeProduct, setRecipeProduct] = useState<Product | null>(null);

    // Filtered groups for prep products (only raw_material)
    const filteredIngredientGroups = useMemo(() => {
        if (!recipeProduct?.is_prep) return ingredientGroups;
        return { raw_material: ingredientGroups.raw_material };
    }, [recipeProduct, ingredientGroups]);

    const productForm = useForm({ name: '', unit: 'pcs', selling_price: '', recipe_type: 'unit' as 'unit' | 'batch', is_prep: false });
    const recipeForm = useForm<{ items: EditableRow[]; estimated_yield_per_batch: string }>({ items: [], estimated_yield_per_batch: '' });

    const openCreate = () => {
        setEditing(null);
        productForm.setData({ name: '', unit: 'pcs', selling_price: '', recipe_type: 'unit', is_prep: false });
        productForm.clearErrors();
        setFormOpen(true);
    };

    const openEdit = (p: Product) => {
        setEditing(p);
        productForm.setData({ name: p.name, unit: p.unit, selling_price: String(p.selling_price), recipe_type: p.recipe_type, is_prep: p.is_prep });
        productForm.clearErrors();
        setFormOpen(true);
    };

    const submitProduct = (e: React.FormEvent) => {
        e.preventDefault();
        if (editing) {
            productForm.put(`/products/${editing.id}`, { onSuccess: () => setFormOpen(false) });
        } else {
            productForm.post('/products', { onSuccess: () => setFormOpen(false) });
        }
    };

    const removeProduct = (p: Product) => {
        if (confirm(`Hapus produk "${p.name}"?`)) router.delete(`/products/${p.id}`);
    };

    const openRecipe = (p: Product) => {
        setRecipeProduct(p);
        recipeForm.setData({
            items: p.recipe.map((r) => ({ ingredient_id: String(r.ingredient_id), quantity: String(r.quantity) })),
            estimated_yield_per_batch: String(p.estimated_yield_per_batch ?? ''),
        });
        recipeForm.clearErrors();
    };

    const addRow = () => recipeForm.setData('items', [...recipeForm.data.items, { ingredient_id: '', quantity: '' }]);
    const removeRow = (i: number) =>
        recipeForm.setData('items', recipeForm.data.items.filter((_, idx) => idx !== i));
    const updateRow = (i: number, key: keyof EditableRow, value: string) =>
        recipeForm.setData(
            'items',
            recipeForm.data.items.map((row, idx) => (idx === i ? { ...row, [key]: value } : row)),
        );

    // Preview COGS/margin live (otoritatif tetap di backend saat disimpan).
    const preview = useMemo(() => {
        const cogs = recipeForm.data.items.reduce((sum, row) => {
            const ing = ingredients.find((x) => String(x.id) === row.ingredient_id);
            const qty = parseFloat(row.quantity) || 0;
            return sum + (ing ? ing.unit_price * qty : 0);
        }, 0);
        const price = recipeProduct?.selling_price ?? 0;
        const margin = price > 0 ? ((price - cogs) / price) * 100 : 0;
        return { cogs, margin };
    }, [recipeForm.data.items, ingredients, recipeProduct]);

    const saveRecipe = (e: React.FormEvent) => {
        e.preventDefault();
        if (!recipeProduct) return;
        recipeForm.transform((data) => ({
            items: data.items
                .filter((r) => r.ingredient_id && r.quantity)
                .map((r) => ({ ingredient_id: Number(r.ingredient_id), quantity: Number(r.quantity) })),
            estimated_yield_per_batch: data.estimated_yield_per_batch ? Number(data.estimated_yield_per_batch) : null,
        }));
        recipeForm.put(`/products/${recipeProduct.id}/recipe`, { onSuccess: () => setRecipeProduct(null) });
    };

    return (
        <AppLayout title="Produk & Resep">
            <Head title="Produk & Resep" />

            <div className="mb-4 flex items-center justify-between">
                <p className="text-sm text-muted-foreground">{products.length} produk</p>
                <Button onClick={openCreate}>
                    <Plus className="h-4 w-4" /> Tambah Produk
                </Button>
            </div>

            <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                {products.map((p) => (
                    <Card key={p.id}>
                        <CardHeader className="flex-row items-start justify-between space-y-0">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    {p.name}
                                    <Badge variant={p.recipe_type === 'batch' ? 'default' : 'secondary'} className="text-xs">
                                        {p.recipe_type === 'batch' ? 'Batch' : 'Unit'}
                                    </Badge>
                                    {p.is_prep && (
                                        <Badge variant="outline" className="text-xs text-orange-600 border-orange-300">
                                            Prep
                                        </Badge>
                                    )}
                                </CardTitle>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {formatRupiah(p.selling_price)} / {p.unit}
                                </p>
                                {p.recipe_type === 'batch' && p.estimated_yield_per_batch && (
                                    <p className="mt-1 text-xs text-blue-600">
                                        Estimasi: {p.estimated_yield_per_batch} pcs/batch
                                    </p>
                                )}
                            </div>
                            {!p.is_active && <Badge variant="secondary">Nonaktif</Badge>}
                        </CardHeader>
                        <CardContent>
                            <div className="grid grid-cols-2 gap-3 rounded-lg bg-muted/50 p-3">
                                <div>
                                    <p className="text-xs text-muted-foreground">COGS</p>
                                    <p className="font-semibold">{formatRupiah(p.cogs)}</p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">Margin</p>
                                    <p className="font-semibold text-success">{formatPercent(p.margin)}</p>
                                </div>
                            </div>
                            <p className="mt-3 text-xs text-muted-foreground">{p.recipe.length} bahan dalam resep</p>
                            <div className="mt-3 flex gap-2">
                                <Button variant="outline" size="sm" onClick={() => openRecipe(p)}>
                                    <Utensils className="h-4 w-4" /> Resep
                                </Button>
                                <Button variant="ghost" size="icon" onClick={() => openEdit(p)}>
                                    <Pencil className="h-4 w-4" />
                                </Button>
                                <Button variant="ghost" size="icon" onClick={() => removeProduct(p)}>
                                    <Trash2 className="h-4 w-4 text-destructive" />
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                ))}
            </div>

            <Modal open={formOpen} onClose={() => setFormOpen(false)} title={editing ? 'Edit Produk' : 'Tambah Produk'}>
                <form onSubmit={submitProduct} className="space-y-4">
                    <div>
                        <Label htmlFor="name">Nama produk</Label>
                        <Input id="name" value={productForm.data.name} onChange={(e) => productForm.setData('name', e.target.value)} />
                        {productForm.errors.name && <p className="mt-1 text-xs text-destructive">{productForm.errors.name}</p>}
                    </div>
                    <div>
                        <Label htmlFor="recipe_type">Tipe Resep</Label>
                        <Select value={productForm.data.recipe_type} onChange={(e) => productForm.setData('recipe_type', e.target.value as 'unit' | 'batch')}>
                            <option value="unit">Unit (per porsi)</option>
                            <option value="batch">Batch (per produksi)</option>
                        </Select>
                        {productForm.errors.recipe_type && <p className="mt-1 text-xs text-destructive">{productForm.errors.recipe_type}</p>}
                    </div>
                    {productForm.data.recipe_type === 'batch' && (
                        <div>
                            <Label htmlFor="is_prep">Kategori</Label>
                            <Select
                                value={productForm.data.is_prep ? 'true' : 'false'}
                                onChange={(e) => productForm.setData('is_prep', e.target.value === 'true')}
                            >
                                <option value="false">Produk Jadi</option>
                                <option value="true">Prep - Bahan Setengah Jadi</option>
                            </Select>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Prep akan masuk ke Stok Prep, bukan Stok Produk Jadi. Bisa jadi bahan resep produk lain.
                            </p>
                            {productForm.errors.is_prep && <p className="mt-1 text-xs text-destructive">{productForm.errors.is_prep}</p>}
                        </div>
                    )}
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <Label htmlFor="unit">Satuan jual</Label>
                            <Input id="unit" value={productForm.data.unit} onChange={(e) => productForm.setData('unit', e.target.value)} placeholder="pcs, cup, porsi" />
                        </div>
                        <div>
                            <Label htmlFor="selling_price">Harga jual (Rp)</Label>
                            <Input id="selling_price" type="number" step="1" value={productForm.data.selling_price} onChange={(e) => productForm.setData('selling_price', e.target.value)} />
                            {productForm.errors.selling_price && <p className="mt-1 text-xs text-destructive">{productForm.errors.selling_price}</p>}
                        </div>
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={productForm.processing}>
                            Simpan
                        </Button>
                    </div>
                </form>
            </Modal>

            <Modal
                open={!!recipeProduct}
                onClose={() => setRecipeProduct(null)}
                title={`Resep - ${recipeProduct?.name ?? ''}`}
                className="max-w-2xl"
            >
                <form onSubmit={saveRecipe} className="space-y-4">
                    {recipeProduct?.is_prep && (
                        <p className="rounded-md bg-orange-50 p-2 text-xs text-orange-700 border border-orange-200">
                            Produk prep hanya boleh menggunakan bahan baku (raw material). Bahan prep lainnya tidak tersedia.
                        </p>
                    )}
                    <div className="space-y-2 max-h-80 overflow-y-auto pr-1">
                        {recipeForm.data.items.map((row, i) => {
                            const ing = ingredients.find((x) => String(x.id) === row.ingredient_id);
                            const cost = ing ? ing.unit_price * (parseFloat(row.quantity) || 0) : 0;
                            return (
                                <div key={i} className="flex items-end gap-2">
                                    <div className="flex-1">
                                        <Label className="text-xs">Bahan</Label>
                                        <Select value={row.ingredient_id} onChange={(e) => updateRow(i, 'ingredient_id', e.target.value)}>
                                            <option value="">- pilih -</option>
                                            {Object.entries(filteredIngredientGroups).map(([key, group]) =>
                                                group.items.length > 0 ? (
                                                    <optgroup key={key} label={group.label}>
                                                        {group.items.map((x) => (
                                                            <option key={x.id} value={x.id}>
                                                                {x.name} ({x.base_unit})
                                                            </option>
                                                        ))}
                                                    </optgroup>
                                                ) : null
                                            )}
                                        </Select>
                                    </div>
                                    <div className="w-28">
                                        <Label className="text-xs">Qty {ing ? `(${ing.base_unit})` : ''}</Label>
                                        <Input type="number" step="0.0001" value={row.quantity} onChange={(e) => updateRow(i, 'quantity', e.target.value)} />
                                    </div>
                                    <div className="w-24 pb-2 text-right text-sm text-muted-foreground">{formatRupiah(cost)}</div>
                                    <Button type="button" variant="ghost" size="icon" onClick={() => removeRow(i)}>
                                        <X className="h-4 w-4" />
                                    </Button>
                                </div>
                            );
                        })}
                        {recipeForm.data.items.length === 0 && (
                            <p className="py-2 text-sm text-muted-foreground">Belum ada bahan. Tambahkan minimal satu.</p>
                        )}
                    </div>

                    <Button type="button" variant="outline" size="sm" onClick={addRow}>
                        <Plus className="h-4 w-4" /> Tambah bahan
                    </Button>

                    {recipeProduct?.recipe_type === 'batch' && (
                        <div>
                            <Label htmlFor="recipe_estimated_yield">Estimasi Yield per Batch (pcs)</Label>
                            <Input
                                id="recipe_estimated_yield"
                                type="number"
                                min="1"
                                placeholder="Contoh: 40"
                                value={recipeForm.data.estimated_yield_per_batch}
                                onChange={(e) => recipeForm.setData('estimated_yield_per_batch', e.target.value)}
                            />
                            <p className="mt-1 text-xs text-muted-foreground">
                                Berapa pcs yang dihasilkan dari 1 batch resep ini. Akan jadi panduan saat produksi.
                            </p>
                        </div>
                    )}

                    <div className="grid grid-cols-2 gap-3 rounded-lg bg-muted/50 p-3">
                        <div>
                            <p className="text-xs text-muted-foreground">COGS / porsi</p>
                            <p className="text-lg font-bold">{formatRupiah(preview.cogs)}</p>
                        </div>
                        <div>
                            <p className="text-xs text-muted-foreground">Margin (harga {formatRupiah(recipeProduct?.selling_price ?? 0)})</p>
                            <p className="text-lg font-bold text-success">{formatPercent(preview.margin)}</p>
                        </div>
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={() => setRecipeProduct(null)}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={recipeForm.processing}>
                            Simpan Resep
                        </Button>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}

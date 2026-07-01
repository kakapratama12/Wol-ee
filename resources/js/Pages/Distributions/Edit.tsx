import { Head, Link, useForm, router } from '@inertiajs/react';
import { ArrowLeft, Plus, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import GroupedSelect from '@/Components/ui/grouped-select';

interface Outlet {
    id: number;
    name: string;
    type: string;
}

interface Product {
    id: number;
    name: string;
    unit: string;
}

interface Ingredient {
    id: number;
    name: string;
    base_unit: string;
    item_type: string;
}

interface DistributionItem {
    id: number;
    product_id: number | null;
    ingredient_id: number | null;
    quantity: number;
    unit: string;
}

interface Distribution {
    id: number;
    from_outlet_id: number;
    to_outlet_id: number;
    distributed_at: string;
    notes: string | null;
    items: DistributionItem[];
}

interface Props {
    distribution: Distribution;
    outlets: Outlet[];
    products: Product[];
    ingredients: Ingredient[];
}

export default function DistributionEdit({ distribution, outlets, products, ingredients }: Props) {
    const formatDateForInput = (dateStr: string) => {
        const date = new Date(dateStr);
        const pad = (n: number) => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    };

    const form = useForm({
        from_outlet_id: distribution.from_outlet_id.toString(),
        to_outlet_id: distribution.to_outlet_id.toString(),
        distributed_at: formatDateForInput(distribution.distributed_at),
        notes: distribution.notes || '',
        items: distribution.items.map(item => ({
            item_id: item.product_id?.toString() || item.ingredient_id?.toString() || '',
            item_source: item.product_id ? 'product' : 'ingredient',
            quantity: Number(item.quantity).toString(),
            unit: item.unit,
        })) as Array<{
            item_id: string;
            item_source: string;
            quantity: string;
            unit: string;
        }>,
    });

    const addItem = () => {
        form.setData('items', [
            ...form.data.items,
            { item_id: '', item_source: '', quantity: '', unit: 'gram' },
        ]);
    };

    const removeItem = (index: number) => {
        const items = [...form.data.items];
        items.splice(index, 1);
        form.setData('items', items);
    };

    const updateItem = (index: number, field: string, value: string) => {
        const items = [...form.data.items];
        (items[index] as any)[field] = value;

        // Auto-detect source and fill unit when item changes
        if (field === 'item_id' && value) {
            const ingredient = ingredients.find((i) => i.id === Number(value));
            if (ingredient) {
                items[index].item_source = 'ingredient';
                items[index].unit = ingredient.base_unit;
            } else {
                const product = products.find((p) => p.id === Number(value));
                if (product) {
                    items[index].item_source = 'product';
                    items[index].unit = product.unit;
                }
            }
        }

        form.setData('items', items);
    };

    const handleSubmit = () => {
        router.put(route('distributions.update', distribution.id), {
            from_outlet_id: form.data.from_outlet_id,
            to_outlet_id: form.data.to_outlet_id,
            distributed_at: form.data.distributed_at,
            notes: form.data.notes,
            items: form.data.items,
        }, {
            onSuccess: () => {
                // Redirect handled by controller
            },
        });
    };

    return (
        <AppLayout title="Edit Distribusi">
            <Head title="Edit Distribusi" />

            <div className="space-y-6">
                {/* Back button */}
                <div>
                    <Link
                        href={route('distributions.index')}
                        className="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        <ArrowLeft className="mr-1 h-4 w-4" />
                        Kembali ke Daftar Distribusi
                    </Link>
                </div>

                {/* Title */}
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Edit Distribusi</h1>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Ubah data distribusi #{distribution.id}
                    </p>
                </div>

                <Card>
                    <CardContent className="p-6">
                        <div className="space-y-4">
                            {/* Outlet selects */}
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label>Dari Outlet</Label>
                                    <select
                                        value={form.data.from_outlet_id}
                                        onChange={(e) => form.setData('from_outlet_id', e.target.value)}
                                        className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    >
                                        <option value="">Pilih outlet</option>
                                        {outlets.map((o) => (
                                            <option key={o.id} value={o.id}>{o.name}</option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <Label>Ke Outlet</Label>
                                    <select
                                        value={form.data.to_outlet_id}
                                        onChange={(e) => form.setData('to_outlet_id', e.target.value)}
                                        className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                                    >
                                        <option value="">Pilih outlet</option>
                                        {outlets
                                            .filter((o) => o.id.toString() !== form.data.from_outlet_id)
                                            .map((o) => (
                                                <option key={o.id} value={o.id}>{o.name}</option>
                                            ))}
                                    </select>
                                </div>
                            </div>

                            {/* Date and Notes */}
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <Label>Tanggal</Label>
                                    <Input
                                        type="datetime-local"
                                        value={form.data.distributed_at}
                                        onChange={(e) => form.setData('distributed_at', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <Label>Catatan</Label>
                                    <Input
                                        value={form.data.notes}
                                        onChange={(e) => form.setData('notes', e.target.value)}
                                        placeholder="Opsional"
                                    />
                                </div>
                            </div>

                            {/* Items */}
                            <div>
                                <div className="flex items-center justify-between mb-2">
                                    <Label>Item</Label>
                                    <Button type="button" variant="ghost" size="sm" onClick={addItem}>
                                        <Plus className="mr-1 h-3 w-3" /> Tambah
                                    </Button>
                                </div>
                                <div className="space-y-2">
                                    {form.data.items.map((item, index) => (
                                        <div key={index} className="flex items-center gap-2">
                                            <GroupedSelect
                                                className="flex-1"
                                                value={item.item_id ? `${item.item_source}:${item.item_id}` : ''}
                                                onChange={(val) => {
                                                    const [source, id] = val.split(':');
                                                    updateItem(index, 'item_id', id);
                                                    updateItem(index, 'item_source', source);
                                                }}
                                                placeholder="Pilih item"
                                                groups={[
                                                    {
                                                        label: 'Bahan Baku',
                                                        options: ingredients
                                                            .filter((i) => i.item_type === 'raw_material')
                                                            .map((i) => ({ value: `ingredient:${i.id}`, label: i.name })),
                                                    },
                                                    {
                                                        label: 'Prep',
                                                        options: ingredients
                                                            .filter((i) => i.item_type === 'prep')
                                                            .map((i) => ({ value: `ingredient:${i.id}`, label: i.name })),
                                                    },
                                                    {
                                                        label: 'Produk Jadi',
                                                        options: products.map((p) => ({ value: `product:${p.id}`, label: p.name })),
                                                    },
                                                ]}
                                            />
                                            <Input
                                                type="number"
                                                value={item.quantity}
                                                onChange={(e) => updateItem(index, 'quantity', e.target.value)}
                                                placeholder="Qty"
                                                className="w-24"
                                            />
                                            <span className="text-sm text-gray-500 w-12">{item.unit}</span>
                                            {form.data.items.length > 1 && (
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => removeItem(index)}
                                                >
                                                    <X className="h-4 w-4 text-red-500" />
                                                </Button>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>

                            {/* Action buttons */}
                            <div className="flex justify-end gap-2 pt-2">
                                <Link href={route('distributions.index')}>
                                    <Button variant="ghost" type="button">
                                        Batal
                                    </Button>
                                </Link>
                                <Button onClick={handleSubmit} disabled={form.processing}>
                                    Simpan Perubahan
                                </Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

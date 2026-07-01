import { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { Plus, X, Eye } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import Modal from '@/Components/ui/modal';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { formatDate } from '@/lib/format';
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

interface DistributionItem {
    id: number;
    product_id: number | null;
    ingredient_id: number | null;
    product: { name: string } | null;
    ingredient: { name: string } | null;
    quantity: number;
    unit: string;
}

interface Distribution {
    id: number;
    from_outlet: Outlet | null;
    to_outlet: Outlet | null;
    items: DistributionItem[];
    notes: string | null;
    distributed_at: string;
}

interface Ingredient {
    id: number;
    name: string;
    base_unit: string;
    item_type: string;
    current_stock: number;
}

interface Props {
    distributions: Distribution[];
    outlets: Outlet[];
    products: Product[];
    ingredients: Ingredient[];
}

export default function DistributionsIndex({ distributions, outlets, products, ingredients }: Props) {
    const [showCreateModal, setShowCreateModal] = useState(false);

    const now = new Date();
    const pad = (n: number) => String(n).padStart(2, '0');
    const defaultDate = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;

    const form = useForm({
        from_outlet_id: outlets.find((o) => o.type === 'pusat')?.id?.toString() || '',
        to_outlet_id: '',
        distributed_at: defaultDate,
        notes: '',
        items: [{ item_id: '', item_source: '', quantity: '', unit: 'gram' }] as Array<{
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
        form.post(route('distributions.store'), {
            onSuccess: () => {
                form.reset();
                setShowCreateModal(false);
            },
        });
    };

    return (
        <AppLayout title="Distribusi">
            <Head title="Distribusi" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Distribusi</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Kirim produk dari pusat ke outlet
                        </p>
                    </div>
                    <Button onClick={() => setShowCreateModal(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Distribusi Baru
                    </Button>
                </div>

                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Tanggal</TableHead>
                                    <TableHead>Dari</TableHead>
                                    <TableHead>Ke</TableHead>
                                    <TableHead>Item</TableHead>
                                    <TableHead>Catatan</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {distributions.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={5} className="text-center text-gray-500 dark:text-gray-400 py-8">
                                            Belum ada distribusi
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    distributions.map((dist) => (
                                        <TableRow key={dist.id} className="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800" onClick={() => router.visit(route('distributions.show', dist.id))}>
                                            <TableCell>{formatDate(dist.distributed_at)}</TableCell>
                                            <TableCell>{dist.from_outlet?.name || '-'}</TableCell>
                                            <TableCell>{dist.to_outlet?.name || '-'}</TableCell>
                                            <TableCell>
                                                {dist.items.map((item) => (
                                                    <div key={item.id} className="text-sm">
                                                        {item.product?.name || item.ingredient?.name || '-'}: {Number(item.quantity)} {item.unit}
                                                    </div>
                                                ))}
                                            </TableCell>
                                            <TableCell className="text-gray-500 dark:text-gray-400">
                                                {dist.notes || '-'}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            {/* Create Modal */}
            <Modal open={showCreateModal} onClose={() => setShowCreateModal(false)} title="Distribusi Baru" size="lg">
                <div className="max-h-[60vh]">
                    <div className="space-y-4">
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

                        <div>
                            <div className="flex items-center justify-between mb-2">
                                <Label>Item</Label>
                                <Button type="button" variant="ghost" size="sm" onClick={addItem}>
                                    <Plus className="mr-1 h-3 w-3" /> Tambah
                                </Button>
                            </div>
                            <div className="space-y-2">
                                {form.data.items.map((item, index) => (
                                    <div key={index} className="flex items-center gap-2 flex-wrap">
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
                                            className="w-24 min-w-[6rem]"
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
                                        {item.item_id && item.item_source === 'ingredient' && (
                                            <span className="text-xs text-gray-400 whitespace-nowrap">
                                                Stok: {Number(ingredients.find((i) => i.id === Number(item.item_id))?.current_stock ?? 0)}
                                            </span>
                                        )}
                                        {item.item_id && item.item_source === 'product' && (
                                            <span className="text-xs text-gray-400 whitespace-nowrap">
                                                Produk jadi
                                            </span>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="flex justify-end gap-2 pt-2">
                            <Button variant="ghost" onClick={() => setShowCreateModal(false)}>
                                Batal
                            </Button>
                            <Button onClick={handleSubmit} disabled={form.processing}>
                                Simpan Distribusi
                            </Button>
                        </div>
                    </div>
                </div>
            </Modal>
        </AppLayout>
    );
}
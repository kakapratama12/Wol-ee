import { useState } from 'react';
import { Plus, X } from 'lucide-react';
import { InertiaFormProps } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
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
    current_stock: number;
}

interface DistributionFormData {
    from_outlet_id: string;
    to_outlet_id: string;
    distributed_at: string;
    notes: string;
    items: Array<{
        item_id: string;
        item_source: string;
        quantity: string;
        unit: string;
    }>;
}

interface Props {
    form: InertiaFormProps<DistributionFormData>;
    outlets: Outlet[];
    products: Product[];
    ingredients: Ingredient[];
    onSubmit: () => void;
    submitLabel: string;
    processing: boolean;
}

export default function DistributionForm({
    form,
    outlets,
    products,
    ingredients,
    onSubmit,
    submitLabel,
    processing,
}: Props) {
    const [formErrors, setFormErrors] = useState<{ from?: string; to?: string }>({});
    const [stockErrors, setStockErrors] = useState<Record<number, string>>({});

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
        // Clear stock error for removed item
        const newErrors = { ...stockErrors };
        delete newErrors[index];
        setStockErrors(newErrors);
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
        // Validate outlets
        const newFormErrors: { from?: string; to?: string } = {};
        if (!form.data.from_outlet_id) {
            newFormErrors.from = 'Pilih Dari Outlet';
        }
        if (!form.data.to_outlet_id) {
            newFormErrors.to = 'Pilih Ke Outlet';
        }
        if (Object.keys(newFormErrors).length > 0) {
            setFormErrors(newFormErrors);
            return;
        }
        setFormErrors({});

        // Validate stock
        const errors: Record<number, string> = {};
        form.data.items.forEach((item, index) => {
            if (item.item_id && item.item_source === 'ingredient' && item.quantity) {
                const ingredient = ingredients.find((i) => i.id === Number(item.item_id));
                if (ingredient && Number(item.quantity) > Number(ingredient.current_stock)) {
                    errors[index] = `Stok ${ingredient.name} hanya ${Number(ingredient.current_stock)} ${ingredient.base_unit}`;
                }
            }
        });

        if (Object.keys(errors).length > 0) {
            setStockErrors(errors);
            return;
        }
        setStockErrors({});

        onSubmit();
    };

    return (
        <div className="space-y-4">
            {/* Outlet selects */}
            <div className="grid grid-cols-2 gap-4">
                <div>
                    <Label>Dari Outlet</Label>
                    <select
                        value={form.data.from_outlet_id ?? ''}
                        onChange={(e) => {
                            form.setData('from_outlet_id', e.target.value);
                            setFormErrors((prev) => ({ ...prev, from: undefined }));
                        }}
                        className={`w-full rounded-md border bg-white px-3 py-2 text-sm dark:bg-gray-800 dark:text-white ${
                            formErrors.from ? 'border-red-500' : 'border-gray-300 dark:border-gray-600'
                        }`}
                    >
                        <option value="">Gudang Pusat</option>
                        {outlets.map((o) => (
                            <option key={o.id} value={o.id}>{o.name}</option>
                        ))}
                    </select>
                    {formErrors.from && <p className="text-xs text-red-500 mt-1">{formErrors.from}</p>}
                </div>
                <div>
                    <Label>Ke Outlet</Label>
                    <select
                        value={form.data.to_outlet_id}
                        onChange={(e) => {
                            form.setData('to_outlet_id', e.target.value);
                            setFormErrors((prev) => ({ ...prev, to: undefined }));
                        }}
                        className={`w-full rounded-md border bg-white px-3 py-2 text-sm dark:bg-gray-800 dark:text-white ${
                            formErrors.to ? 'border-red-500' : 'border-gray-300 dark:border-gray-600'
                        }`}
                    >
                        <option value="">Pilih outlet</option>
                        {outlets
                            .filter((o) => !form.data.from_outlet_id || o.id.toString() !== form.data.from_outlet_id)
                            .map((o) => (
                                <option key={o.id} value={o.id}>{o.name}</option>
                            ))}
                    </select>
                    {formErrors.to && <p className="text-xs text-red-500 mt-1">{formErrors.to}</p>}
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
                        <div key={index}>
                            <div className="flex items-center gap-2">
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
                            </div>
                            {/* Stock info & error below */}
                            <div className="ml-1 mt-0.5">
                                {item.item_id && item.item_source === 'ingredient' && (
                                    <span className="text-xs text-gray-400">
                                        Sisa stok: {Number(ingredients.find((i) => i.id === Number(item.item_id))?.current_stock ?? 0)} {item.unit}
                                    </span>
                                )}
                                {item.item_id && item.item_source === 'product' && (
                                    <span className="text-xs text-gray-400">
                                        Produk jadi
                                    </span>
                                )}
                                {stockErrors[index] && (
                                    <div className="text-xs text-red-500 mt-0.5">{stockErrors[index]}</div>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            {/* Action buttons */}
            <div className="flex justify-end gap-2 pt-2">
                <Button onClick={handleSubmit} disabled={processing}>
                    {submitLabel}
                </Button>
            </div>
        </div>
    );
}

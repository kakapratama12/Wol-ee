import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, History, Pencil } from 'lucide-react';
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
import GroupedSelect from '@/Components/ui/grouped-select';
import { formatDate } from '@/lib/format';

interface Outlet {
    id: number;
    name: string;
    type: string;
}

interface InventoryItem {
    id: number;
    product: { id: number; name: string } | null;
    ingredient: { id: number; name: string; base_unit: string } | null;
    quantity: number;
    unit: string;
    last_updated: string;
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

interface Props {
    outlet: Outlet;
    inventory: InventoryItem[];
    products: Product[];
    ingredients: Ingredient[];
}

export default function OutletInventory({ outlet, inventory, products, ingredients }: Props) {
    const [adjustingItem, setAdjustingItem] = useState<InventoryItem | null>(null);
    const [selectedItem, setSelectedItem] = useState('');
    const [adjustment, setAdjustment] = useState<number>(0);

    const form = useForm({
        item_id: 0,
        item_source: '',
        adjustment: 0,
        unit: '',
    });

    const groupedOptions = [
        {
            label: 'Produk',
            options: products.map((p) => ({
                value: `product-${p.id}`,
                label: p.name,
            })),
        },
        {
            label: 'Bahan Baku',
            options: ingredients.map((i) => ({
                value: `ingredient-${i.id}`,
                label: i.name,
            })),
        },
    ];

    const openAdjustModal = (item: InventoryItem) => {
        setAdjustingItem(item);
        setSelectedItem('');
        setAdjustment(0);

        if (item.product) {
            setSelectedItem(`product-${item.product.id}`);
            form.setData({
                item_id: item.product.id,
                item_source: 'product',
                adjustment: 0,
                unit: item.unit,
            });
        } else if (item.ingredient) {
            setSelectedItem(`ingredient-${item.ingredient.id}`);
            form.setData({
                item_id: item.ingredient.id,
                item_source: 'ingredient',
                adjustment: 0,
                unit: item.unit,
            });
        }
    };

    const handleItemSelect = (value: string) => {
        setSelectedItem(value);
        const [source, id] = value.split('-');
        const sourceId = parseInt(id);

        if (source === 'product') {
            const product = products.find((p) => p.id === sourceId);
            form.setData({
                item_id: sourceId,
                item_source: 'product',
                adjustment: adjustment,
                unit: product?.unit || '',
            });
        } else if (source === 'ingredient') {
            const ingredient = ingredients.find((i) => i.id === sourceId);
            form.setData({
                item_id: sourceId,
                item_source: 'ingredient',
                adjustment: adjustment,
                unit: ingredient?.base_unit || '',
            });
        }
    };

    const handleAdjustmentChange = (value: number) => {
        setAdjustment(value);
        form.setData('adjustment', value);
    };

    const handleSubmit = () => {
        form.post(route('outlets.inventory.adjust', outlet.id), {
            onSuccess: () => {
                setAdjustingItem(null);
                setSelectedItem('');
                setAdjustment(0);
                form.reset();
            },
        });
    };

    const getItemName = (item: InventoryItem) => {
        return item.product?.name || item.ingredient?.name || '-';
    };

    const getItemType = (item: InventoryItem) => {
        return item.product ? 'Produk' : 'Bahan Baku';
    };

    return (
        <AppLayout title={`Stok Outlet - ${outlet.name}`}>
            <Head title={`Stok - ${outlet.name}`} />

            <div className="space-y-6">
                {/* Back Button & Header */}
                <div className="flex items-center gap-4">
                    <Link
                        href={route('outlets.index')}
                        className="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                    >
                        <ArrowLeft className="mr-1 h-4 w-4" />
                        Kembali
                    </Link>
                </div>

                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                            Stok - {outlet.name}
                        </h1>
                        <span className="mt-1 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300">
                            {outlet.type === 'pusat' ? 'Pusat' : 'Outlet'}
                        </span>
                    </div>
                    <Link
                        href={route('outlets.stock.movements.page', outlet.id)}
                        className="inline-flex items-center gap-1.5 rounded-md bg-gray-100 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
                    >
                        <History className="h-4 w-4" />
                        Riwayat
                    </Link>
                </div>

                {/* Inventory Table */}
                <Card>
                    <CardContent className="p-0">
                        {inventory.length === 0 ? (
                            <div className="p-8 text-center text-gray-500 dark:text-gray-400">
                                Belum ada stok di outlet ini
                            </div>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-12">No</TableHead>
                                        <TableHead>Item</TableHead>
                                        <TableHead>Tipe</TableHead>
                                        <TableHead className="text-right">Stok</TableHead>
                                        <TableHead>Satuan</TableHead>
                                        <TableHead>Terakhir Update</TableHead>
                                        <TableHead className="text-right">Aksi</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {inventory.map((item, index) => (
                                        <TableRow key={item.id}>
                                            <TableCell>{index + 1}</TableCell>
                                            <TableCell className="font-medium">
                                                {getItemName(item)}
                                            </TableCell>
                                            <TableCell>
                                                <span
                                                    className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                        item.product
                                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                                                            : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300'
                                                    }`}
                                                >
                                                    {getItemType(item)}
                                                </span>
                                            </TableCell>
                                            <TableCell className="text-right font-mono">
                                                {item.quantity.toLocaleString('id-ID')}
                                            </TableCell>
                                            <TableCell>{item.unit}</TableCell>
                                            <TableCell>{formatDate(item.last_updated)}</TableCell>
                                            <TableCell className="text-right">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => openAdjustModal(item)}
                                                >
                                                    <Pencil className="mr-1 h-4 w-4" />
                                                    Adjust
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Adjust Modal */}
            <Modal
                open={!!adjustingItem}
                onClose={() => {
                    setAdjustingItem(null);
                    setSelectedItem('');
                    setAdjustment(0);
                }}
                title="Penyesuaian Stok"
            >
                <div className="space-y-4">
                    {adjustingItem && (
                        <>
                            <div className="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                                <p className="text-sm text-gray-500 dark:text-gray-400">
                                    Item saat ini
                                </p>
                                <p className="font-medium text-gray-900 dark:text-white">
                                    {getItemName(adjustingItem)}
                                </p>
                            </div>

                            <div>
                                <Label>Pilih Item</Label>
                                <GroupedSelect
                                    groups={groupedOptions}
                                    value={selectedItem}
                                    onChange={handleItemSelect}
                                    placeholder="Pilih item untuk disesuaikan"
                                />
                                {form.errors.item_id && (
                                    <p className="mt-1 text-sm text-red-500">{form.errors.item_id}</p>
                                )}
                            </div>

                            <div>
                                <Label htmlFor="adjustment">Penyesuaian</Label>
                                <Input
                                    id="adjustment"
                                    type="number"
                                    value={adjustment}
                                    onChange={(e) => handleAdjustmentChange(Number(e.target.value))}
                                    placeholder="Masukkan nilai penyesuaian"
                                    step="0.01"
                                />
                                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    Positif = tambah stok, Negatif = kurangi stok
                                </p>
                                {form.errors.adjustment && (
                                    <p className="mt-1 text-sm text-red-500">{form.errors.adjustment}</p>
                                )}
                            </div>

                            <div className="rounded-lg bg-blue-50 p-4 dark:bg-blue-900/20">
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-500 dark:text-gray-400">
                                        Stok saat ini:
                                    </span>
                                    <span className="font-medium text-gray-900 dark:text-white">
                                        {adjustingItem.quantity.toLocaleString('id-ID')} {adjustingItem.unit}
                                    </span>
                                </div>
                                <div className="mt-2 flex justify-between text-sm">
                                    <span className="text-gray-500 dark:text-gray-400">
                                        Stok setelah adjust:
                                    </span>
                                    <span className="font-bold text-blue-600 dark:text-blue-400">
                                        {(adjustingItem.quantity + adjustment).toLocaleString('id-ID')} {adjustingItem.unit}
                                    </span>
                                </div>
                            </div>

                            <div className="flex justify-end gap-2 pt-4">
                                <Button
                                    variant="ghost"
                                    onClick={() => {
                                        setAdjustingItem(null);
                                        setSelectedItem('');
                                        setAdjustment(0);
                                    }}
                                >
                                    Batal
                                </Button>
                                <Button onClick={handleSubmit} disabled={form.processing}>
                                    Simpan Penyesuaian
                                </Button>
                            </div>
                        </>
                    )}
                </div>
            </Modal>
        </AppLayout>
    );
}

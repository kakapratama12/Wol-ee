import { useState } from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import {
    ArrowLeft, ArrowUpCircle, ArrowDownCircle, ShoppingCart, Wrench,
    Pencil, MapPin, Package, History,
} from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import Modal from '@/Components/ui/modal';
import {
    Table, TableBody, TableCell, TableHead, TableHeader, TableRow,
} from '@/Components/ui/table';
import GroupedSelect from '@/Components/ui/grouped-select';
import { formatDate } from '@/lib/format';

/* ── Types ── */
interface Outlet {
    id: number;
    name: string;
    type: 'pusat' | 'outlet';
    address: string | null;
    is_active: boolean;
    inventory_count: number;
}

interface InventoryItem {
    id: number;
    product: { id: number; name: string } | null;
    ingredient: { id: number; name: string; base_unit: string } | null;
    quantity: number;
    unit: string;
    last_updated: string;
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

interface Product { id: number; name: string; unit: string; }
interface Ingredient { id: number; name: string; base_unit: string; item_type: string; }

interface Props {
    outlet: Outlet;
    inventory: InventoryItem[];
    movements: Movement[];
    products: Product[];
    ingredients: Ingredient[];
}

/* ── Movement helpers ── */
const typeLabels: Record<string, string> = {
    purchase: 'Pembelian', adjustment: 'Adjustmen', usage: 'Pemakaian',
    production_input: 'Produksi', production_output: 'Hasil Produksi', waste: 'Waste',
};
const reasonLabels: Record<string, string> = {
    rusak: 'Rusak', expired: 'Expired', susut: 'Susut', lainnya: 'Lainnya',
};
function getTypeIcon(type: string) {
    switch (type) {
        case 'purchase': return <ShoppingCart className="h-4 w-4 text-green-600 dark:text-green-400" />;
        case 'adjustment': return <Wrench className="h-4 w-4 text-amber-600 dark:text-amber-400" />;
        case 'usage': case 'production_input': return <ArrowDownCircle className="h-4 w-4 text-red-600 dark:text-red-400" />;
        default: return <ArrowUpCircle className="h-4 w-4 text-blue-600 dark:text-blue-400" />;
    }
}

type Tab = 'stok' | 'riwayat';

export default function OutletShow({ outlet, inventory, movements, products, ingredients }: Props) {
    const [activeTab, setActiveTab] = useState<Tab>('stok');
    const [showEditModal, setShowEditModal] = useState(false);
    const [adjustingItem, setAdjustingItem] = useState<InventoryItem | null>(null);
    const [selectedItem, setSelectedItem] = useState('');
    const [adjustment, setAdjustment] = useState<number>(0);

    /* ── Edit form ── */
    const editForm = useForm({
        name: outlet.name,
        type: outlet.type,
        address: outlet.address || '',
        is_active: outlet.is_active,
    });

    /* ── Adjust form ── */
    const adjustForm = useForm({ item_id: 0, item_source: '', adjustment: 0, unit: '' });

    const groupedOptions = [
        { label: 'Produk', options: products.map((p) => ({ value: `product-${p.id}`, label: p.name })) },
        { label: 'Bahan Baku', options: ingredients.map((i) => ({ value: `ingredient-${i.id}`, label: i.name })) },
    ];

    const openAdjustModal = (item: InventoryItem) => {
        setAdjustingItem(item);
        setSelectedItem('');
        setAdjustment(0);
        if (item.product) {
            setSelectedItem(`product-${item.product.id}`);
            adjustForm.setData({ item_id: item.product.id, item_source: 'product', adjustment: 0, unit: item.unit });
        } else if (item.ingredient) {
            setSelectedItem(`ingredient-${item.ingredient.id}`);
            adjustForm.setData({ item_id: item.ingredient.id, item_source: 'ingredient', adjustment: 0, unit: item.unit });
        }
    };

    const handleItemSelect = (value: string) => {
        setSelectedItem(value);
        const [source, id] = value.split('-');
        const sourceId = parseInt(id);
        if (source === 'product') {
            const product = products.find((p) => p.id === sourceId);
            adjustForm.setData({ item_id: sourceId, item_source: 'product', adjustment, unit: product?.unit || '' });
        } else {
            const ingredient = ingredients.find((i) => i.id === sourceId);
            adjustForm.setData({ item_id: sourceId, item_source: 'ingredient', adjustment, unit: ingredient?.base_unit || '' });
        }
    };

    const handleAdjustmentChange = (value: number) => {
        setAdjustment(value);
        adjustForm.setData('adjustment', value);
    };

    const handleAdjustSubmit = () => {
        adjustForm.post(route('outlets.inventory.adjust', outlet.id), {
            onSuccess: () => { setAdjustingItem(null); setSelectedItem(''); setAdjustment(0); adjustForm.reset(); },
        });
    };

    const getItemName = (item: InventoryItem) => item.product?.name || item.ingredient?.name || '-';
    const getItemType = (item: InventoryItem) => item.product ? 'Produk' : 'Bahan Baku';

    return (
        <AppLayout title={`Detail - ${outlet.name}`}>
            <Head title={`Detail - ${outlet.name}`} />

            <div className="space-y-6">
                {/* Back + Header */}
                <div className="flex items-center gap-4">
                    <Link
                        href={route('outlets.index')}
                        className="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                    >
                        <ArrowLeft className="mr-1 h-4 w-4" />
                        Kembali
                    </Link>
                </div>

                {/* Outlet Info Card */}
                <Card>
                    <CardContent className="p-6">
                        <div className="flex items-start justify-between">
                            <div className="space-y-2">
                                <h1 className="text-2xl font-bold text-gray-900 dark:text-white">{outlet.name}</h1>
                                <div className="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                                    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                        outlet.type === 'pusat'
                                            ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-300'
                                            : 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                                    }`}>
                                        {outlet.type === 'pusat' ? 'Pusat / Dapur' : 'Outlet'}
                                    </span>
                                    {outlet.address && (
                                        <span className="inline-flex items-center gap-1">
                                            <MapPin className="h-3.5 w-3.5" />
                                            {outlet.address}
                                        </span>
                                    )}
                                    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                        outlet.is_active
                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                                            : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                    }`}>
                                        {outlet.is_active ? 'Aktif' : 'Nonaktif'}
                                    </span>
                                </div>
                            </div>
                            <Button variant="outline" size="sm" onClick={() => setShowEditModal(true)}>
                                <Pencil className="mr-1.5 h-4 w-4" />
                                Edit
                            </Button>
                        </div>
                    </CardContent>
                </Card>

                {/* Tabs */}
                <div className="flex gap-1 rounded-lg bg-gray-100 p-1 dark:bg-gray-800">
                    <button
                        onClick={() => setActiveTab('stok')}
                        className={`flex items-center gap-1.5 rounded-md px-4 py-2 text-sm font-medium transition-colors ${
                            activeTab === 'stok'
                                ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                                : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
                        }`}
                    >
                        <Package className="h-4 w-4" />
                        Stok
                        <span className="ml-1 rounded-full bg-gray-200 px-1.5 text-xs dark:bg-gray-600">{inventory.length}</span>
                    </button>
                    <button
                        onClick={() => setActiveTab('riwayat')}
                        className={`flex items-center gap-1.5 rounded-md px-4 py-2 text-sm font-medium transition-colors ${
                            activeTab === 'riwayat'
                                ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white'
                                : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300'
                        }`}
                    >
                        <History className="h-4 w-4" />
                        Riwayat
                        <span className="ml-1 rounded-full bg-gray-200 px-1.5 text-xs dark:bg-gray-600">{movements.length}</span>
                    </button>
                </div>

                {/* Tab: Stok */}
                {activeTab === 'stok' && (
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
                                                <TableCell className="font-medium">{getItemName(item)}</TableCell>
                                                <TableCell>
                                                    <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                        item.product
                                                            ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                                                            : 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300'
                                                    }`}>
                                                        {getItemType(item)}
                                                    </span>
                                                </TableCell>
                                                <TableCell className="text-right font-mono">
                                                    {item.quantity.toLocaleString('id-ID')}
                                                </TableCell>
                                                <TableCell>{item.unit}</TableCell>
                                                <TableCell>{formatDate(item.last_updated)}</TableCell>
                                                <TableCell className="text-right">
                                                    <Button variant="ghost" size="sm" onClick={() => openAdjustModal(item)}>
                                                        <Wrench className="mr-1 h-4 w-4" />
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
                )}

                {/* Tab: Riwayat */}
                {activeTab === 'riwayat' && (
                    <Card>
                        <CardContent className="p-0">
                            {movements.length === 0 ? (
                                <div className="p-8 text-center text-gray-500 dark:text-gray-400">
                                    Belum ada riwayat pergerakan stok.
                                </div>
                            ) : (
                                <div className="divide-y divide-gray-200 dark:divide-gray-700">
                                    {movements.map((movement) => (
                                        <div key={movement.id} className="flex items-start justify-between p-4 hover:bg-gray-50 dark:hover:bg-gray-800/50">
                                            <div className="flex items-start gap-3">
                                                <div className="mt-0.5">{getTypeIcon(movement.type)}</div>
                                                <div>
                                                    <p className="text-sm font-medium text-gray-900 dark:text-white">{movement.ingredient}</p>
                                                    <p className="text-xs text-gray-500 dark:text-gray-400">
                                                        {typeLabels[movement.type] ?? movement.type}
                                                        {movement.reason && <> · {reasonLabels[movement.reason] ?? movement.reason}</>}
                                                    </p>
                                                    {movement.note && (
                                                        <p className="mt-1 text-xs text-gray-400 dark:text-gray-500 italic">
                                                            "{movement.note}"
                                                        </p>
                                                    )}
                                                    <p className="mt-1 text-xs text-gray-400 dark:text-gray-500">
                                                        oleh {movement.user} · {movement.occurred_at}
                                                    </p>
                                                </div>
                                            </div>
                                            <div className="text-right">
                                                <p className={`text-sm font-semibold ${movement.quantity > 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}`}>
                                                    {movement.quantity > 0 ? '+' : ''}{Number(movement.quantity).toLocaleString('id-ID')}
                                                </p>
                                                <p className="text-xs text-gray-400 dark:text-gray-500">
                                                    Sisa: {Number(movement.stock_after).toLocaleString('id-ID')}
                                                </p>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>

            {/* Edit Modal */}
            <Modal open={showEditModal} onClose={() => setShowEditModal(false)} title="Edit Outlet">
                <div className="space-y-4">
                    <div>
                        <Label htmlFor="edit-name">Nama Outlet</Label>
                        <Input id="edit-name" value={editForm.data.name} onChange={(e) => editForm.setData('name', e.target.value)} />
                    </div>
                    <div>
                        <Label htmlFor="edit-type">Tipe</Label>
                        <select
                            id="edit-type"
                            value={editForm.data.type}
                            onChange={(e) => editForm.setData('type', e.target.value as 'pusat' | 'outlet')}
                            className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800 dark:text-white"
                        >
                            <option value="pusat">Pusat / Dapur</option>
                            <option value="outlet">Outlet</option>
                        </select>
                    </div>
                    <div>
                        <Label htmlFor="edit-address">Alamat</Label>
                        <Input id="edit-address" value={editForm.data.address} onChange={(e) => editForm.setData('address', e.target.value)} />
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button variant="ghost" onClick={() => setShowEditModal(false)}>Batal</Button>
                        <Button onClick={() => editForm.put(route('outlets.update', outlet.id), { onSuccess: () => setShowEditModal(false) })} disabled={editForm.processing}>
                            Update
                        </Button>
                    </div>
                </div>
            </Modal>

            {/* Adjust Stock Modal */}
            <Modal
                open={!!adjustingItem}
                onClose={() => { setAdjustingItem(null); setSelectedItem(''); setAdjustment(0); }}
                title="Penyesuaian Stok"
            >
                <div className="space-y-4">
                    {adjustingItem && (
                        <>
                            <div className="rounded-lg bg-gray-50 p-4 dark:bg-gray-800">
                                <p className="text-sm text-gray-500 dark:text-gray-400">Item saat ini</p>
                                <p className="font-medium text-gray-900 dark:text-white">{getItemName(adjustingItem)}</p>
                            </div>
                            <div>
                                <Label>Pilih Item</Label>
                                <GroupedSelect groups={groupedOptions} value={selectedItem} onChange={handleItemSelect} placeholder="Pilih item untuk disesuaikan" />
                                {adjustForm.errors.item_id && <p className="mt-1 text-sm text-red-500">{adjustForm.errors.item_id}</p>}
                            </div>
                            <div>
                                <Label htmlFor="adjustment">Penyesuaian</Label>
                                <Input id="adjustment" type="number" value={adjustment} onChange={(e) => handleAdjustmentChange(Number(e.target.value))} step="0.01" />
                                <p className="mt-1 text-sm text-gray-500 dark:text-gray-400">Positif = tambah stok, Negatif = kurangi stok</p>
                                {adjustForm.errors.adjustment && <p className="mt-1 text-sm text-red-500">{adjustForm.errors.adjustment}</p>}
                            </div>
                            <div className="rounded-lg bg-blue-50 p-4 dark:bg-blue-900/20">
                                <div className="flex justify-between text-sm">
                                    <span className="text-gray-500 dark:text-gray-400">Stok saat ini:</span>
                                    <span className="font-medium text-gray-900 dark:text-white">{adjustingItem.quantity.toLocaleString('id-ID')} {adjustingItem.unit}</span>
                                </div>
                                <div className="mt-2 flex justify-between text-sm">
                                    <span className="text-gray-500 dark:text-gray-400">Stok setelah adjust:</span>
                                    <span className="font-bold text-blue-600 dark:text-blue-400">{(adjustingItem.quantity + adjustment).toLocaleString('id-ID')} {adjustingItem.unit}</span>
                                </div>
                            </div>
                            <div className="flex justify-end gap-2 pt-4">
                                <Button variant="ghost" onClick={() => { setAdjustingItem(null); setSelectedItem(''); setAdjustment(0); }}>Batal</Button>
                                <Button onClick={handleAdjustSubmit} disabled={adjustForm.processing}>Simpan Penyesuaian</Button>
                            </div>
                        </>
                    )}
                </div>
            </Modal>
        </AppLayout>
    );
}

import { useState } from 'react';
import { Head, useForm, router, usePage } from '@inertiajs/react';
import { Plus, Pencil, SlidersHorizontal, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import StockStatusBadge from '@/Components/StockStatusBadge';
import Modal from '@/Components/ui/modal';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select } from '@/Components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { formatRupiah, formatNumber } from '@/lib/format';

interface Ingredient {
    id: number;
    name: string;
    item_type: string;
    unit_type: string;
    base_unit: string;
    unit_price: number;
    current_stock: number;
    minimum_stock: number;
    supplier: string | null;
    status: string;
}

interface Props {
    ingredients: Ingredient[];
    itemType: string;
    counts: Record<string, number>;
    canManage: boolean;
}

const emptyForm = {
    name: '',
    item_type: 'raw_material',
    unit_type: 'gramasi',
    base_unit: 'g',
    unit_price: '',
    current_stock: '',
    minimum_stock: '',
};

export default function InventoryIndex({ ingredients, itemType, counts, canManage }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Ingredient | null>(null);
    const [adjusting, setAdjusting] = useState<Ingredient | null>(null);

    const form = useForm<Record<string, string>>({ ...emptyForm });
    const adjustForm = useForm({ current_stock: '', note: '' });

    const tabs = [
        { key: 'raw_material', label: 'Bahan Dasar', count: counts.raw_material ?? 0 },
        { key: 'finished_goods', label: 'Produk Jadi', count: counts.finished_goods ?? 0 },
    ];

    const switchTab = (type: string) => {
        router.get('/inventory', { type }, { preserveState: true, replace: true });
    };

    const openCreate = () => {
        setEditing(null);
        form.setData({ ...emptyForm, item_type: itemType });
        form.clearErrors();
        setFormOpen(true);
    };

    const openEdit = (ing: Ingredient) => {
        setEditing(ing);
        form.setData({
            name: ing.name,
            unit_type: ing.unit_type,
            base_unit: ing.base_unit,
            unit_price: String(ing.unit_price),
            current_stock: String(ing.current_stock),
            minimum_stock: String(ing.minimum_stock),
        });
        form.clearErrors();
        setFormOpen(true);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editing) {
            form.put(`/inventory/${editing.id}`, { onSuccess: () => setFormOpen(false) });
        } else {
            form.post('/inventory', { onSuccess: () => setFormOpen(false) });
        }
    };

    const openAdjust = (ing: Ingredient) => {
        setAdjusting(ing);
        adjustForm.setData({ current_stock: String(ing.current_stock), note: '' });
        adjustForm.clearErrors();
    };

    const submitAdjust = (e: React.FormEvent) => {
        e.preventDefault();
        if (!adjusting) return;
        adjustForm.post(`/inventory/${adjusting.id}/adjust`, { onSuccess: () => setAdjusting(null) });
    };

    const remove = (ing: Ingredient) => {
        if (confirm(`Hapus bahan "${ing.name}"?`)) {
            router.delete(`/inventory/${ing.id}`);
        }
    };

    return (
        <AppLayout title="Inventory">
            <Head title="Inventory" />

            <div className="mb-4 flex items-center justify-between">
                <p className="text-sm text-muted-foreground">{ingredients.length} item</p>
                {canManage && (
                    <Button onClick={openCreate} disabled={itemType === 'finished_goods'}>
                        <Plus className="h-4 w-4" /> Tambah Bahan
                    </Button>
                )}
            </div>

            {/* Tabs */}
            <div className="mb-4 flex gap-1 rounded-lg bg-muted p-1">
                {tabs.map((tab) => (
                    <button
                        key={tab.key}
                        onClick={() => switchTab(tab.key)}
                        className={`flex-1 rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                            itemType === tab.key
                                ? 'bg-background text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        }`}
                    >
                        {tab.label}
                        <span className="ml-1.5 rounded-full bg-muted-foreground/20 px-1.5 py-0.5 text-xs">
                            {tab.count}
                        </span>
                    </button>
                ))}
            </div>

            <Card>
                <CardContent className="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Bahan</TableHead>
                                <TableHead>Tipe</TableHead>
                                <TableHead>Harga / unit</TableHead>
                                <TableHead>Stok</TableHead>
                                <TableHead>Min</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead className="text-right">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {ingredients.length === 0 && (
                                <TableRow>
                                    <TableCell colSpan={7} className="py-8 text-center text-muted-foreground">
                                        Belum ada bahan.
                                    </TableCell>
                                </TableRow>
                            )}
                            {ingredients.map((ing) => (
                                <TableRow key={ing.id}>
                                    <TableCell className="font-medium">{ing.name}</TableCell>
                                    <TableCell className="capitalize text-muted-foreground">{ing.unit_type}</TableCell>
                                    <TableCell>
                                        {formatRupiah(ing.unit_price)}
                                        <span className="text-muted-foreground">/{ing.base_unit}</span>
                                    </TableCell>
                                    <TableCell>
                                        {formatNumber(ing.current_stock, 2)} {ing.base_unit}
                                    </TableCell>
                                    <TableCell className="text-muted-foreground">
                                        {formatNumber(ing.minimum_stock, 2)} {ing.base_unit}
                                    </TableCell>
                                    <TableCell>
                                        <StockStatusBadge status={ing.status} />
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex justify-end gap-1">
                                            <Button variant="ghost" size="icon" title="Sesuaikan stok" onClick={() => openAdjust(ing)}>
                                                <SlidersHorizontal className="h-4 w-4" />
                                            </Button>
                                            {canManage && (
                                                <>
                                                    <Button variant="ghost" size="icon" title="Edit" onClick={() => openEdit(ing)}>
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button variant="ghost" size="icon" title="Hapus" onClick={() => remove(ing)}>
                                                        <Trash2 className="h-4 w-4 text-destructive" />
                                                    </Button>
                                                </>
                                            )}
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <Modal open={formOpen} onClose={() => setFormOpen(false)} title={editing ? 'Edit Bahan' : 'Tambah Bahan'}>
                <form onSubmit={submit} className="space-y-4">
                    <input type="hidden" value={form.data.item_type} />
                    <div>
                        <Label htmlFor="name">Nama</Label>
                        <Input id="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} />
                        {form.errors.name && <p className="mt-1 text-xs text-destructive">{form.errors.name}</p>}
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <Label htmlFor="unit_type">Tipe</Label>
                            <Select id="unit_type" value={form.data.unit_type} onChange={(e) => form.setData('unit_type', e.target.value)}>
                                <option value="gramasi">Gramasi</option>
                                <option value="packaged">Packaged</option>
                            </Select>
                        </div>
                        <div>
                            <Label htmlFor="base_unit">Satuan dasar</Label>
                            <Input id="base_unit" value={form.data.base_unit} onChange={(e) => form.setData('base_unit', e.target.value)} placeholder="g, ml, butir, sachet" />
                            {form.errors.base_unit && <p className="mt-1 text-xs text-destructive">{form.errors.base_unit}</p>}
                        </div>
                    </div>
                    <div>
                        <Label htmlFor="unit_price">Harga per satuan dasar (Rp)</Label>
                        <Input id="unit_price" type="number" step="0.0001" value={form.data.unit_price} onChange={(e) => form.setData('unit_price', e.target.value)} />
                        {form.errors.unit_price && <p className="mt-1 text-xs text-destructive">{form.errors.unit_price}</p>}
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        {!editing && (
                            <div>
                                <Label htmlFor="current_stock">Stok awal</Label>
                                <Input id="current_stock" type="number" step="0.0001" value={form.data.current_stock} onChange={(e) => form.setData('current_stock', e.target.value)} />
                            </div>
                        )}
                        <div>
                            <Label htmlFor="minimum_stock">Stok minimum</Label>
                            <Input id="minimum_stock" type="number" step="0.0001" value={form.data.minimum_stock} onChange={(e) => form.setData('minimum_stock', e.target.value)} />
                            {form.errors.minimum_stock && <p className="mt-1 text-xs text-destructive">{form.errors.minimum_stock}</p>}
                        </div>
                    </div>
                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Simpan
                        </Button>
                    </div>
                </form>
            </Modal>

            <Modal open={!!adjusting} onClose={() => setAdjusting(null)} title={`Sesuaikan Stok - ${adjusting?.name ?? ''}`}>
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
                        {adjustForm.errors.current_stock && <p className="mt-1 text-xs text-destructive">{adjustForm.errors.current_stock}</p>}
                    </div>
                    <div>
                        <Label htmlFor="adjust_note">Catatan (opsional)</Label>
                        <Input id="adjust_note" value={adjustForm.data.note} onChange={(e) => adjustForm.setData('note', e.target.value)} />
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

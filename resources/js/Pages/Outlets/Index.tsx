import { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { Plus, Pencil, Trash2, MapPin } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
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

interface Outlet {
    id: number;
    name: string;
    address: string | null;
    is_active: boolean;
    inventory_count: number;
}

interface Props {
    outlets: Outlet[];
}

export default function OutletsIndex({ outlets }: Props) {
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [editing, setEditing] = useState<Outlet | null>(null);

    const form = useForm({
        name: '',
        address: '',
    });

    const editForm = useForm({
        name: '',
        address: '',
        is_active: true,
    });

    const handleCreate = () => {
        form.post(route('outlets.store'), {
            onSuccess: () => {
                form.reset();
                setShowCreateModal(false);
            },
        });
    };

    const handleUpdate = () => {
        if (!editing) return;
        editForm.put(route('outlets.update', editing.id), {
            onSuccess: () => {
                editForm.reset();
                setEditing(null);
            },
        });
    };

    const handleDelete = (outlet: Outlet) => {
        if (!confirm(`Nonaktifkan "${outlet.name}"?`)) return;
        router.delete(route('outlets.destroy', outlet.id));
    };

    const startEdit = (outlet: Outlet) => {
        editForm.setData({
            name: outlet.name,
            address: outlet.address || '',
            is_active: outlet.is_active,
        });
        setEditing(outlet);
    };

    return (
        <AppLayout title="Outlet">
            <Head title="Outlet" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Outlet</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Kelola outlet kamu
                        </p>
                    </div>
                    <Button onClick={() => setShowCreateModal(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Tambah Outlet
                    </Button>
                </div>

                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Nama</TableHead>
                                    <TableHead>Alamat</TableHead>
                                    <TableHead className="text-center">Stok Item</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">Aksi</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {outlets.map((outlet) => (
                                    <TableRow
                                        key={outlet.id}
                                        className="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/50"
                                        onClick={() => router.visit(route('outlets.show', outlet.id))}
                                    >
                                        <TableCell className="font-medium">{outlet.name}</TableCell>
                                        <TableCell className="text-gray-500 dark:text-gray-400">
                                            {outlet.address || '-'}
                                        </TableCell>
                                        <TableCell className="text-center text-gray-700 dark:text-gray-300">{outlet.inventory_count}</TableCell>
                                        <TableCell>
                                            <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                outlet.is_active
                                                    ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300'
                                                    : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300'
                                            }`}>
                                                {outlet.is_active ? 'Aktif' : 'Nonaktif'}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex items-center justify-end gap-2">
                                                <Button variant="ghost" size="sm" onClick={(e) => { e.stopPropagation(); startEdit(outlet); }}>
                                                    <Pencil className="h-4 w-4" />
                                                </Button>
                                                {outlet.is_active && (
                                                    <Button variant="ghost" size="sm" onClick={(e) => { e.stopPropagation(); handleDelete(outlet); }}>
                                                        <Trash2 className="h-4 w-4 text-red-500" />
                                                    </Button>
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            {/* Create Modal */}
            <Modal open={showCreateModal} onClose={() => setShowCreateModal(false)} title="Tambah Outlet">
                <div>
                    <div className="space-y-4">
                        <div>
                            <Label htmlFor="name">Nama Outlet</Label>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                placeholder="Contoh: Outlet Bandung"
                            />
                            {form.errors.name && (
                                <p className="text-sm text-red-500 mt-1">{form.errors.name}</p>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="address">Alamat</Label>
                            <Input
                                id="address"
                                value={form.data.address}
                                onChange={(e) => form.setData('address', e.target.value)}
                                placeholder="Opsional"
                            />
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button variant="ghost" onClick={() => setShowCreateModal(false)}>
                                Batal
                            </Button>
                            <Button onClick={handleCreate} disabled={form.processing}>
                                Simpan
                            </Button>
                        </div>
                    </div>
                </div>
            </Modal>

            {/* Edit Modal */}
            <Modal open={!!editing} onClose={() => setEditing(null)} title="Edit Outlet">
                <div>
                    <div className="space-y-4">
                        <div>
                            <Label htmlFor="edit-name">Nama Outlet</Label>
                            <Input
                                id="edit-name"
                                value={editForm.data.name}
                                onChange={(e) => editForm.setData('name', e.target.value)}
                            />
                        </div>
                        <div>
                            <Label htmlFor="edit-address">Alamat</Label>
                            <Input
                                id="edit-address"
                                value={editForm.data.address}
                                onChange={(e) => editForm.setData('address', e.target.value)}
                            />
                        </div>
                        <div className="flex justify-end gap-2">
                            <Button variant="ghost" onClick={() => setEditing(null)}>
                                Batal
                            </Button>
                            <Button onClick={handleUpdate} disabled={editForm.processing}>
                                Update
                            </Button>
                        </div>
                    </div>
                </div>
            </Modal>
        </AppLayout>
    );
}

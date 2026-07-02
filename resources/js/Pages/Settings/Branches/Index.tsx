import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/ui/modal';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';

interface Branch {
    id: number;
    name: string;
    address: string | null;
    is_active: boolean;
    users_count: number;
}

interface Props {
    branches: Branch[];
}

export default function BranchesIndex({ branches }: Props) {
    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState<Branch | null>(null);

    const createForm = useForm({
        name: '',
        address: '',
        is_active: true,
    });

    const editForm = useForm({
        name: '',
        address: '',
        is_active: true,
    });

    const openEdit = (branch: Branch) => {
        setEditing(branch);
        editForm.setData({
            name: branch.name,
            address: branch.address ?? '',
            is_active: branch.is_active,
        });
    };

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/settings/branches', {
            onSuccess: () => {
                createForm.reset();
                setShowCreate(false);
            },
        });
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editing) return;
        editForm.put(`/settings/branches/${editing.id}`, {
            onSuccess: () => setEditing(null),
        });
    };

    const destroy = (branch: Branch) => {
        if (!confirm(`Hapus cabang "${branch.name}"?`)) return;
        router.delete(`/settings/branches/${branch.id}`);
    };

    return (
        <AppLayout title="Cabang / Outlet">
            <Head title="Cabang" />

            <div className="mb-4 flex items-center justify-between">
                <p className="text-sm text-muted-foreground">
                    Kelola cabang untuk assign kasir POS.
                </p>
                <Button type="button" onClick={() => setShowCreate(true)}>
                    <Plus className="mr-2 h-4 w-4" />
                    Tambah Cabang
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Daftar Cabang</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama</TableHead>
                                <TableHead>Alamat</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Kasir</TableHead>
                                <TableHead className="w-24" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {branches.map((branch) => (
                                <TableRow key={branch.id}>
                                    <TableCell className="font-medium">{branch.name}</TableCell>
                                    <TableCell>{branch.address || '—'}</TableCell>
                                    <TableCell>
                                        {branch.is_active ? 'Aktif' : 'Nonaktif'}
                                    </TableCell>
                                    <TableCell>{branch.users_count}</TableCell>
                                    <TableCell>
                                        <div className="flex gap-1">
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => openEdit(branch)}
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() => destroy(branch)}
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

            <Modal open={showCreate} onClose={() => setShowCreate(false)} title="Tambah Cabang">
                <form onSubmit={submitCreate} className="space-y-4">
                    <div>
                        <Label htmlFor="create-name">Nama</Label>
                        <Input
                            id="create-name"
                            value={createForm.data.name}
                            onChange={(e) => createForm.setData('name', e.target.value)}
                        />
                    </div>
                    <div>
                        <Label htmlFor="create-address">Alamat</Label>
                        <Input
                            id="create-address"
                            value={createForm.data.address}
                            onChange={(e) => createForm.setData('address', e.target.value)}
                        />
                    </div>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={createForm.data.is_active}
                            onChange={(e) => createForm.setData('is_active', e.target.checked)}
                        />
                        Aktif
                    </label>
                    <Button type="submit" disabled={createForm.processing}>
                        Simpan
                    </Button>
                </form>
            </Modal>

            <Modal open={!!editing} onClose={() => setEditing(null)} title="Edit Cabang">
                <form onSubmit={submitEdit} className="space-y-4">
                    <div>
                        <Label htmlFor="edit-name">Nama</Label>
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
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={editForm.data.is_active}
                            onChange={(e) => editForm.setData('is_active', e.target.checked)}
                        />
                        Aktif
                    </label>
                    <Button type="submit" disabled={editForm.processing}>
                        Simpan
                    </Button>
                </form>
            </Modal>
        </AppLayout>
    );
}

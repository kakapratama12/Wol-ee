import { useState } from 'react';
import { Head, useForm, router } from '@inertiajs/react';
import { Plus, Pencil, Trash2, Key, UserCog } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select } from '@/Components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { formatDate } from '@/lib/format';

interface StaffUser {
    id: number;
    name: string;
    email: string;
    outlet_id: number | null;
    outlet_name: string;
    created_at: string | null;
}

interface Outlet {
    id: number;
    name: string;
}

export default function StaffIndex({ staff, outlets }: { staff: StaffUser[]; outlets: Outlet[] }) {
    const [showAdd, setShowAdd] = useState(false);
    const [editing, setEditing] = useState<StaffUser | null>(null);
    const [resetPasswordFor, setResetPasswordFor] = useState<StaffUser | null>(null);

    const addForm = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        outlet_id: '',
    });

    const editForm = useForm({
        name: '',
        email: '',
        outlet_id: '',
    });

    const passwordForm = useForm({
        password: '',
        password_confirmation: '',
    });

    const handleAdd = (e: React.FormEvent) => {
        e.preventDefault();
        addForm.post(route('staff.store'), {
            onSuccess: () => {
                addForm.reset();
                setShowAdd(false);
            },
        });
    };

    const handleEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editing) return;
        editForm.put(route('staff.update', editing.id), {
            onSuccess: () => {
                setEditing(null);
                editForm.reset();
            },
        });
    };

    const handleResetPassword = (e: React.FormEvent) => {
        e.preventDefault();
        if (!resetPasswordFor) return;
        passwordForm.put(route('staff.password', resetPasswordFor.id), {
            onSuccess: () => {
                setResetPasswordFor(null);
                passwordForm.reset();
            },
        });
    };

    const handleDelete = (staff: StaffUser) => {
        if (!confirm(`Hapus staff "${staff.name}"?`)) return;
        router.delete(route('staff.destroy', staff.id));
    };

    const openEdit = (s: StaffUser) => {
        setEditing(s);
        editForm.setData({
            name: s.name,
            email: s.email,
            outlet_id: s.outlet_id?.toString() || '',
        });
    };

    return (
        <AppLayout title="Kelola Staff">
            <Head title="Kelola Staff" />

            <div className="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm text-muted-foreground">
                    {staff.length} staff terdaftar
                </p>
                <Button onClick={() => setShowAdd(!showAdd)}>
                    <Plus className="mr-2 h-4 w-4" /> Tambah Staff
                </Button>
            </div>

            {/* Add Staff Form */}
            {showAdd && (
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>Tambah Staff Baru</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleAdd} className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="add-name">Nama</Label>
                                    <Input
                                        id="add-name"
                                        value={addForm.data.name}
                                        onChange={(e) => addForm.setData('name', e.target.value)}
                                        className="mt-1"
                                    />
                                    {addForm.errors.name && (
                                        <p className="mt-1 text-xs text-destructive">{addForm.errors.name}</p>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="add-email">Email</Label>
                                    <Input
                                        id="add-email"
                                        type="email"
                                        value={addForm.data.email}
                                        onChange={(e) => addForm.setData('email', e.target.value)}
                                        className="mt-1"
                                    />
                                    {addForm.errors.email && (
                                        <p className="mt-1 text-xs text-destructive">{addForm.errors.email}</p>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="add-outlet">Outlet</Label>
                                    <Select
                                        id="add-outlet"
                                        value={addForm.data.outlet_id}
                                        onChange={(e) => addForm.setData('outlet_id', e.target.value)}
                                        className="mt-1"
                                    >
                                        <option value="">Pilih outlet...</option>
                                        {outlets.map((o) => (
                                            <option key={o.id} value={o.id}>{o.name}</option>
                                        ))}
                                    </Select>
                                    {addForm.errors.outlet_id && (
                                        <p className="mt-1 text-xs text-destructive">{addForm.errors.outlet_id}</p>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="add-password">Password</Label>
                                    <Input
                                        id="add-password"
                                        type="password"
                                        value={addForm.data.password}
                                        onChange={(e) => addForm.setData('password', e.target.value)}
                                        className="mt-1"
                                    />
                                    {addForm.errors.password && (
                                        <p className="mt-1 text-xs text-destructive">{addForm.errors.password}</p>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="add-password-confirm">Konfirmasi Password</Label>
                                    <Input
                                        id="add-password-confirm"
                                        type="password"
                                        value={addForm.data.password_confirmation}
                                        onChange={(e) => addForm.setData('password_confirmation', e.target.value)}
                                        className="mt-1"
                                    />
                                </div>
                            </div>
                            <div className="flex gap-2">
                                <Button type="submit" disabled={addForm.processing}>Simpan</Button>
                                <Button type="button" variant="ghost" onClick={() => { setShowAdd(false); addForm.reset(); }}>
                                    Batal
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            )}

            {/* Staff Table */}
            <Card>
                <CardContent className="p-0">
                    <div className="overflow-x-auto">
<Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Outlet</TableHead>
                                <TableHead>Dibuat</TableHead>
                                <TableHead className="w-24"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {staff.length === 0 ? (
                                <TableRow>
                                    <TableCell colSpan={5} className="text-center text-muted-foreground py-8">
                                        Belum ada staff
                                    </TableCell>
                                </TableRow>
                            ) : (
                                staff.map((s) => (
                                    <TableRow key={s.id}>
                                        <TableCell className="font-medium">{s.name}</TableCell>
                                        <TableCell className="text-muted-foreground">{s.email}</TableCell>
                                        <TableCell>
                                            {s.outlet_id ? (
                                                <span className="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary">
                                                    {s.outlet_name}
                                                </span>
                                            ) : (
                                                <span className="text-xs text-muted-foreground">Belum di-assign</span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-muted-foreground text-sm">
                                            {formatDate(s.created_at)}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex gap-1">
                                                <button
                                                    type="button"
                                                    onClick={() => openEdit(s)}
                                                    className="rounded p-1 hover:bg-accent"
                                                    title="Edit"
                                                >
                                                    <Pencil className="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => setResetPasswordFor(s)}
                                                    className="rounded p-1 hover:bg-accent"
                                                    title="Reset Password"
                                                >
                                                    <Key className="h-4 w-4" />
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => handleDelete(s)}
                                                    className="rounded p-1 hover:bg-destructive/10 text-destructive"
                                                    title="Hapus"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
</div>
                </CardContent>
            </Card>

            {/* Edit Modal */}
            {editing && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <Card className="w-full max-w-md">
                        <CardHeader>
                            <CardTitle>Edit Staff</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleEdit} className="space-y-4">
                                <div>
                                    <Label htmlFor="edit-name">Nama</Label>
                                    <Input
                                        id="edit-name"
                                        value={editForm.data.name}
                                        onChange={(e) => editForm.setData('name', e.target.value)}
                                        className="mt-1"
                                    />
                                    {editForm.errors.name && (
                                        <p className="mt-1 text-xs text-destructive">{editForm.errors.name}</p>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="edit-email">Email</Label>
                                    <Input
                                        id="edit-email"
                                        type="email"
                                        value={editForm.data.email}
                                        onChange={(e) => editForm.setData('email', e.target.value)}
                                        className="mt-1"
                                    />
                                    {editForm.errors.email && (
                                        <p className="mt-1 text-xs text-destructive">{editForm.errors.email}</p>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="edit-outlet">Outlet</Label>
                                    <Select
                                        id="edit-outlet"
                                        value={editForm.data.outlet_id}
                                        onChange={(e) => editForm.setData('outlet_id', e.target.value)}
                                        className="mt-1"
                                    >
                                        <option value="">Pilih outlet...</option>
                                        {outlets.map((o) => (
                                            <option key={o.id} value={o.id}>{o.name}</option>
                                        ))}
                                    </Select>
                                    {editForm.errors.outlet_id && (
                                        <p className="mt-1 text-xs text-destructive">{editForm.errors.outlet_id}</p>
                                    )}
                                </div>
                                <div className="flex gap-2">
                                    <Button type="submit" disabled={editForm.processing}>Simpan</Button>
                                    <Button type="button" variant="ghost" onClick={() => { setEditing(null); editForm.reset(); }}>
                                        Batal
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            )}

            {/* Reset Password Modal */}
            {resetPasswordFor && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <Card className="w-full max-w-md">
                        <CardHeader>
                            <CardTitle>Reset Password — {resetPasswordFor.name}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleResetPassword} className="space-y-4">
                                <div>
                                    <Label htmlFor="reset-password">Password Baru</Label>
                                    <Input
                                        id="reset-password"
                                        type="password"
                                        value={passwordForm.data.password}
                                        onChange={(e) => passwordForm.setData('password', e.target.value)}
                                        className="mt-1"
                                    />
                                    {passwordForm.errors.password && (
                                        <p className="mt-1 text-xs text-destructive">{passwordForm.errors.password}</p>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="reset-password-confirm">Konfirmasi Password</Label>
                                    <Input
                                        id="reset-password-confirm"
                                        type="password"
                                        value={passwordForm.data.password_confirmation}
                                        onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)}
                                        className="mt-1"
                                    />
                                </div>
                                <div className="flex gap-2">
                                    <Button type="submit" disabled={passwordForm.processing}>Reset</Button>
                                    <Button type="button" variant="ghost" onClick={() => { setResetPasswordFor(null); passwordForm.reset(); }}>
                                        Batal
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            )}
        </AppLayout>
    );
}

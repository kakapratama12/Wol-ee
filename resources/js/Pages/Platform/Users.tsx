import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Pencil, Key, ChevronDown, ChevronRight } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select } from '@/Components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { cn } from '@/lib/utils';
import { formatDate } from '@/lib/format';

interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    tenant_id: number;
    tenant: string | null;
    email_verified: boolean;
    created_at: string | null;
}

interface Tenant {
    id: number;
    name: string;
}

interface Props {
    users: User[];
    tenants: Tenant[];
    roles: Record<string, string>;
}

export default function Users({ users, tenants, roles }: Props) {
    const [showAdd, setShowAdd] = useState(false);
    const [editingUser, setEditingUser] = useState<User | null>(null);
    const [resetPasswordUser, setResetPasswordUser] = useState<User | null>(null);

    const addForm = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'pengelola',
        tenant_id: tenants[0]?.id ?? '',
    });

    const editForm = useForm({
        role: '',
        tenant_id: 0,
    });

    const passwordForm = useForm({
        password: '',
        password_confirmation: '',
    });

    const handleAdd = (e: React.FormEvent) => {
        e.preventDefault();
        addForm.post(route('platform.users.store'), {
            onSuccess: () => {
                addForm.reset();
                setShowAdd(false);
            },
        });
    };

    const handleEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingUser) return;
        editForm.put(route('platform.users.update', editingUser.id), {
            onSuccess: () => setEditingUser(null),
        });
    };

    const handleResetPassword = (e: React.FormEvent) => {
        e.preventDefault();
        if (!resetPasswordUser) return;
        passwordForm.put(route('platform.users.password', resetPasswordUser.id), {
            onSuccess: () => {
                passwordForm.reset();
                setResetPasswordUser(null);
            },
        });
    };

    const openEdit = (user: User) => {
        editForm.setData({
            role: user.role,
            tenant_id: user.tenant_id,
        });
        setEditingUser(user);
    };

    const openResetPassword = (user: User) => {
        passwordForm.reset();
        setResetPasswordUser(user);
    };

    return (
        <AppLayout title="User Management">
            <Head title="User Management" />

            <div className="mb-4 flex items-center justify-between">
                <p className="text-sm text-muted-foreground">{users.length} user terdaftar</p>
                <Button onClick={() => setShowAdd(!showAdd)}>
                    <Plus className="mr-2 h-4 w-4" /> Tambah User
                </Button>
            </div>

            {/* Add User Form */}
            {showAdd && (
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>Tambah User Baru</CardTitle>
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
                                <div>
                                    <Label htmlFor="add-role">Role</Label>
                                    <Select
                                        id="add-role"
                                        value={addForm.data.role}
                                        onChange={(e) => addForm.setData('role', e.target.value)}
                                        className="mt-1"
                                    >
                                        {Object.entries(roles).map(([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                                <div>
                                    <Label htmlFor="add-tenant">Usaha</Label>
                                    <Select
                                        id="add-tenant"
                                        value={addForm.data.tenant_id}
                                        onChange={(e) => addForm.setData('tenant_id', Number(e.target.value))}
                                        className="mt-1"
                                    >
                                        {tenants.map((t) => (
                                            <option key={t.id} value={t.id}>
                                                {t.name}
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                            </div>
                            <div className="flex gap-2">
                                <Button type="submit" disabled={addForm.processing}>
                                    Simpan
                                </Button>
                                <Button type="button" variant="ghost" onClick={() => setShowAdd(false)}>
                                    Batal
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            )}

            {/* Users Table */}
            <Card>
                <CardContent className="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Role</TableHead>
                                <TableHead>Usaha</TableHead>
                                <TableHead>Verified</TableHead>
                                <TableHead className="w-24">Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {users.map((user) => (
                                <TableRow key={user.id}>
                                    <TableCell className="font-medium">{user.name}</TableCell>
                                    <TableCell className="text-muted-foreground">{user.email}</TableCell>
                                    <TableCell>
                                        <span
                                            className={cn(
                                                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                                user.role === 'pengelola'
                                                    ? 'bg-primary/10 text-primary'
                                                    : 'bg-muted text-muted-foreground',
                                            )}
                                        >
                                            {roles[user.role] ?? user.role}
                                        </span>
                                    </TableCell>
                                    <TableCell>{user.tenant ?? '-'}</TableCell>
                                    <TableCell>
                                        {user.email_verified ? (
                                            <span className="text-success">✓</span>
                                        ) : (
                                            <span className="text-muted-foreground">—</span>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <div className="flex gap-1">
                                            <button
                                                type="button"
                                                onClick={() => openEdit(user)}
                                                className="rounded p-1 text-muted-foreground hover:bg-accent hover:text-foreground"
                                                title="Edit"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                            <button
                                                type="button"
                                                onClick={() => openResetPassword(user)}
                                                className="rounded p-1 text-muted-foreground hover:bg-accent hover:text-foreground"
                                                title="Reset Password"
                                            >
                                                <Key className="h-4 w-4" />
                                            </button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            {/* Edit User Modal */}
            {editingUser && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <Card className="w-full max-w-md">
                        <CardHeader>
                            <CardTitle>Edit User: {editingUser.name}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleEdit} className="space-y-4">
                                <div>
                                    <Label>Role</Label>
                                    <Select
                                        value={editForm.data.role}
                                        onChange={(e) => editForm.setData('role', e.target.value)}
                                        className="mt-1"
                                    >
                                        {Object.entries(roles).map(([value, label]) => (
                                            <option key={value} value={value}>
                                                {label}
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                                <div>
                                    <Label>Usaha</Label>
                                    <Select
                                        value={editForm.data.tenant_id}
                                        onChange={(e) => editForm.setData('tenant_id', Number(e.target.value))}
                                        className="mt-1"
                                    >
                                        {tenants.map((t) => (
                                            <option key={t.id} value={t.id}>
                                                {t.name}
                                            </option>
                                        ))}
                                    </Select>
                                </div>
                                <div className="flex gap-2">
                                    <Button type="submit" disabled={editForm.processing}>
                                        Simpan
                                    </Button>
                                    <Button type="button" variant="ghost" onClick={() => setEditingUser(null)}>
                                        Batal
                                    </Button>
                                </div>
                            </form>
                        </CardContent>
                    </Card>
                </div>
            )}

            {/* Reset Password Modal */}
            {resetPasswordUser && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <Card className="w-full max-w-md">
                        <CardHeader>
                            <CardTitle>Reset Password: {resetPasswordUser.name}</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleResetPassword} className="space-y-4">
                                <div>
                                    <Label>Password Baru</Label>
                                    <Input
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
                                    <Label>Konfirmasi Password</Label>
                                    <Input
                                        type="password"
                                        value={passwordForm.data.password_confirmation}
                                        onChange={(e) => passwordForm.setData('password_confirmation', e.target.value)}
                                        className="mt-1"
                                    />
                                </div>
                                <div className="flex gap-2">
                                    <Button type="submit" disabled={passwordForm.processing}>
                                        Reset
                                    </Button>
                                    <Button type="button" variant="ghost" onClick={() => setResetPasswordUser(null)}>
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

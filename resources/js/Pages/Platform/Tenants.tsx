import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Search, ChevronDown, ChevronRight, Plus, Pencil } from 'lucide-react';
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
import { formatDate, formatNumber } from '@/lib/format';
import { cn } from '@/lib/utils';

interface PengelolaUser {
    id: number;
    name: string;
    email: string;
}

interface TenantRow {
    id: number;
    name: string;
    slug: string;
    plan: string;
    status: string;
    business_type: string;
    users_count: number;
    pengelola_count: number;
    pengelola_users: PengelolaUser[];
    staff_count: number;
    has_bot_token: boolean;
    ai_usage_today: number;
    feedback_count: number;
    created_at: string | null;
}

export default function Tenants({ tenants }: { tenants: TenantRow[] }) {
    const [search, setSearch] = useState('');
    const [expandedTenant, setExpandedTenant] = useState<number | null>(null);
    const [showAdd, setShowAdd] = useState(false);
    const [editingTenant, setEditingTenant] = useState<TenantRow | null>(null);

    const addForm = useForm({
        name: '',
        plan: 'free',
        business_type: 'single',
        pengelola_name: '',
        pengelola_email: '',
        pengelola_password: '',
        pengelola_password_confirmation: '',
        create_outlet: false,
        outlet_name: '',
        outlet_address: '',
        staff_name: '',
        staff_email: '',
        staff_password: '',
        staff_password_confirmation: '',
    });

    const editForm = useForm({
        name: '',
        slug: '',
        business_type: 'single',
    });

    const handleAdd = (e: React.FormEvent) => {
        e.preventDefault();
        addForm.post(route('platform.tenants.store'), {
            onSuccess: () => {
                addForm.reset();
                setShowAdd(false);
            },
        });
    };

    const handleEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editingTenant) return;
        editForm.put(route('platform.tenants.update', editingTenant.id), {
            onSuccess: () => {
                setEditingTenant(null);
                editForm.reset();
            },
        });
    };

    const openEdit = (tenant: TenantRow) => {
        setEditingTenant(tenant);
        editForm.setData({
            name: tenant.name,
            slug: tenant.slug,
            business_type: tenant.business_type || 'single',
        });
    };

    const generateSlug = (name: string) => {
        return name
            .toLowerCase()
            .replace(/[^a-z0-9\s-]/g, '')
            .replace(/\s+/g, '-')
            .replace(/--+/g, '-')
            .replace(/^-+|-+$/g, '');
    };

    const filteredTenants = tenants.filter(
        (tenant) =>
            search === '' ||
            tenant.name.toLowerCase().includes(search.toLowerCase()) ||
            tenant.slug.toLowerCase().includes(search.toLowerCase()),
    );

    const toggleExpand = (id: number) => {
        setExpandedTenant(expandedTenant === id ? null : id);
    };

    return (
        <AppLayout title="Usaha">
            <Head title="Platform Usaha" />

            <div className="mb-4 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-sm text-muted-foreground">
                    {filteredTenants.length} usaha dari {tenants.length} total
                </p>
                <Button onClick={() => setShowAdd(!showAdd)}>
                    <Plus className="mr-2 h-4 w-4" /> Tambah Usaha
                </Button>
            </div>

            {/* Add Usaha Form */}
            {showAdd && (
                <Card className="mb-6">
                    <CardHeader>
                        <CardTitle>Tambah Usaha Baru</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={handleAdd} className="space-y-4">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="add-name">Nama Usaha</Label>
                                    <Input
                                        id="add-name"
                                        value={addForm.data.name}
                                        onChange={(e) => addForm.setData('name', e.target.value)}
                                        className="mt-1"
                                    />
                                    {addForm.errors.name && (
                                        <p className="mt-1 text-xs text-destructive">
                                            {addForm.errors.name}
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="add-plan">Plan</Label>
                                    <Select
                                        id="add-plan"
                                        value={addForm.data.plan}
                                        onChange={(e) => addForm.setData('plan', e.target.value)}
                                        className="mt-1"
                                    >
                                        <option value="free">Free</option>
                                        <option value="pro">Pro</option>
                                        <option value="business">Business</option>
                                    </Select>
                                </div>
                            </div>

                            <div>
                                <Label htmlFor="add-business-type">Tipe Usaha</Label>
                                <Select
                                    id="add-business-type"
                                    value={addForm.data.business_type}
                                    onChange={(e) => addForm.setData('business_type', e.target.value)}
                                    className="mt-1"
                                >
                                    <option value="single">Single Outlet</option>
                                    <option value="multi">Multi Outlet</option>
                                </Select>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Single = 1 lokasi, owner langsung jadi kasir. Multi = banyak cabang, staff khusus buat POS.
                                </p>
                            </div>

                            <div className="border-t pt-4">
                                <h4 className="mb-3 text-sm font-medium">Pengelola (Owner)</h4>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <Label htmlFor="add-pengelola-name">Nama</Label>
                                        <Input
                                            id="add-pengelola-name"
                                            value={addForm.data.pengelola_name}
                                            onChange={(e) =>
                                                addForm.setData('pengelola_name', e.target.value)
                                            }
                                            className="mt-1"
                                        />
                                        {addForm.errors.pengelola_name && (
                                            <p className="mt-1 text-xs text-destructive">
                                                {addForm.errors.pengelola_name}
                                            </p>
                                        )}
                                    </div>
                                    <div>
                                        <Label htmlFor="add-pengelola-email">Email</Label>
                                        <Input
                                            id="add-pengelola-email"
                                            type="email"
                                            value={addForm.data.pengelola_email}
                                            onChange={(e) =>
                                                addForm.setData('pengelola_email', e.target.value)
                                            }
                                            className="mt-1"
                                        />
                                        {addForm.errors.pengelola_email && (
                                            <p className="mt-1 text-xs text-destructive">
                                                {addForm.errors.pengelola_email}
                                            </p>
                                        )}
                                    </div>
                                    <div>
                                        <Label htmlFor="add-pengelola-password">Password</Label>
                                        <Input
                                            id="add-pengelola-password"
                                            type="password"
                                            value={addForm.data.pengelola_password}
                                            onChange={(e) =>
                                                addForm.setData(
                                                    'pengelola_password',
                                                    e.target.value,
                                                )
                                            }
                                            className="mt-1"
                                        />
                                        {addForm.errors.pengelola_password && (
                                            <p className="mt-1 text-xs text-destructive">
                                                {addForm.errors.pengelola_password}
                                            </p>
                                        )}
                                    </div>
                                    <div>
                                        <Label htmlFor="add-pengelola-password-confirm">
                                            Konfirmasi Password
                                        </Label>
                                        <Input
                                            id="add-pengelola-password-confirm"
                                            type="password"
                                            value={addForm.data.pengelola_password_confirmation}
                                            onChange={(e) =>
                                                addForm.setData(
                                                    'pengelola_password_confirmation',
                                                    e.target.value,
                                                )
                                            }
                                            className="mt-1"
                                        />
                                    </div>
                                </div>
                            </div>

                            {addForm.data.business_type === 'multi' && (
                                <div className="border-t pt-4">
                                    <div className="flex items-center gap-2 mb-3">
                                        <input
                                            type="checkbox"
                                            id="create_outlet"
                                            checked={addForm.data.create_outlet}
                                            onChange={(e) => addForm.setData('create_outlet', e.target.checked)}
                                            className="h-4 w-4 rounded border-gray-300"
                                        />
                                        <Label htmlFor="create_outlet" className="text-sm font-medium">
                                            Buat outlet sekarang
                                        </Label>
                                    </div>
                                    <p className="text-xs text-muted-foreground mb-3">
                                        Centang kalau mau langsung buat outlet + staff. Bisa dibuat nanti.
                                    </p>

                                    {addForm.data.create_outlet && (
                                        <>
                                            <div className="grid gap-4 sm:grid-cols-2 mb-4">
                                                <div>
                                                    <Label htmlFor="add-outlet-name">Nama Outlet</Label>
                                                    <Input
                                                        id="add-outlet-name"
                                                        value={addForm.data.outlet_name}
                                                        onChange={(e) => addForm.setData('outlet_name', e.target.value)}
                                                        placeholder="Outlet Utama"
                                                        className="mt-1"
                                                    />
                                                    {addForm.errors.outlet_name && (
                                                        <p className="mt-1 text-xs text-destructive">
                                                            {addForm.errors.outlet_name}
                                                        </p>
                                                    )}
                                                </div>
                                                <div>
                                                    <Label htmlFor="add-outlet-address">Alamat (opsional)</Label>
                                                    <Input
                                                        id="add-outlet-address"
                                                        value={addForm.data.outlet_address}
                                                        onChange={(e) => addForm.setData('outlet_address', e.target.value)}
                                                        placeholder="Jl. Merdeka No. 1"
                                                        className="mt-1"
                                                    />
                                                </div>
                                            </div>

                                            <div className="border-t pt-4">
                                                <h4 className="mb-3 text-sm font-medium">Staff (Kasir)</h4>
                                                <div className="grid gap-4 sm:grid-cols-2">
                                                    <div>
                                                        <Label htmlFor="add-staff-name">Nama</Label>
                                                        <Input
                                                            id="add-staff-name"
                                                            value={addForm.data.staff_name}
                                                            onChange={(e) => addForm.setData('staff_name', e.target.value)}
                                                            className="mt-1"
                                                        />
                                                        {addForm.errors.staff_name && (
                                                            <p className="mt-1 text-xs text-destructive">
                                                                {addForm.errors.staff_name}
                                                            </p>
                                                        )}
                                                    </div>
                                                    <div>
                                                        <Label htmlFor="add-staff-email">Email</Label>
                                                        <Input
                                                            id="add-staff-email"
                                                            type="email"
                                                            value={addForm.data.staff_email}
                                                            onChange={(e) => addForm.setData('staff_email', e.target.value)}
                                                            className="mt-1"
                                                        />
                                                        {addForm.errors.staff_email && (
                                                            <p className="mt-1 text-xs text-destructive">
                                                                {addForm.errors.staff_email}
                                                            </p>
                                                        )}
                                                    </div>
                                                    <div>
                                                        <Label htmlFor="add-staff-password">Password</Label>
                                                        <Input
                                                            id="add-staff-password"
                                                            type="password"
                                                            value={addForm.data.staff_password}
                                                            onChange={(e) => addForm.setData('staff_password', e.target.value)}
                                                            className="mt-1"
                                                        />
                                                        {addForm.errors.staff_password && (
                                                            <p className="mt-1 text-xs text-destructive">
                                                                {addForm.errors.staff_password}
                                                            </p>
                                                        )}
                                                    </div>
                                                    <div>
                                                        <Label htmlFor="add-staff-password-confirm">Konfirmasi Password</Label>
                                                        <Input
                                                            id="add-staff-password-confirm"
                                                            type="password"
                                                            value={addForm.data.staff_password_confirmation}
                                                            onChange={(e) => addForm.setData('staff_password_confirmation', e.target.value)}
                                                            className="mt-1"
                                                        />
                                                    </div>
                                                </div>
                                            </div>
                                        </>
                                    )}
                                </div>
                            )}

                            <div className="flex gap-2">
                                <Button type="submit" disabled={addForm.processing}>
                                    Simpan
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    onClick={() => setShowAdd(false)}
                                >
                                    Batal
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            )}

            {/* Search */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="relative">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Cari nama usaha..."
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            className="pl-9"
                        />
                    </div>
                </CardContent>
            </Card>

            {/* Tenants Table */}
            <Card>
                <CardContent className="p-0">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead className="w-8"></TableHead>
                                <TableHead>Usaha</TableHead>
                                <TableHead>Plan</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Tipe</TableHead>
                                <TableHead>Users</TableHead>
                                <TableHead>Bot Token</TableHead>
                                <TableHead>AI Hari Ini</TableHead>
                                <TableHead>Feedback</TableHead>
                                <TableHead>Dibuat</TableHead>
                                <TableHead className="w-10"></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {filteredTenants.map((tenant) => (
                                <>
                                    <TableRow key={tenant.id}>
                                        <TableCell>
                                            <button
                                                type="button"
                                                onClick={() => toggleExpand(tenant.id)}
                                                className="rounded p-1 hover:bg-accent"
                                            >
                                                {expandedTenant === tenant.id ? (
                                                    <ChevronDown className="h-4 w-4" />
                                                ) : (
                                                    <ChevronRight className="h-4 w-4" />
                                                )}
                                            </button>
                                        </TableCell>
                                        <TableCell>
                                            <div className="font-medium">{tenant.name}</div>
                                            <div className="text-xs text-muted-foreground">
                                                {tenant.slug}
                                            </div>
                                        </TableCell>
                                        <TableCell className="uppercase text-xs text-muted-foreground">
                                            {tenant.plan}
                                        </TableCell>
                                        <TableCell className="uppercase text-xs text-muted-foreground">
                                            {tenant.status}
                                        </TableCell>
                                        <TableCell className="text-xs">
                                            {tenant.business_type === 'multi' ? (
                                                <span className="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900 dark:text-blue-300">
                                                    Multi Outlet
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">Single</span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {formatNumber(tenant.users_count)}
                                            <span className="ml-1 text-xs text-muted-foreground">
                                                ({tenant.pengelola_count} pengelola,{' '}
                                                {tenant.staff_count} staff)
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            {tenant.has_bot_token ? 'Aktif' : '-'}
                                        </TableCell>
                                        <TableCell>{formatNumber(tenant.ai_usage_today)}</TableCell>
                                        <TableCell>{formatNumber(tenant.feedback_count)}</TableCell>
                                        <TableCell className="text-muted-foreground">
                                            {formatDate(tenant.created_at)}
                                        </TableCell>
                                        <TableCell>
                                            <button
                                                type="button"
                                                onClick={() => openEdit(tenant)}
                                                className="rounded p-1 hover:bg-accent"
                                                title="Edit nama usaha"
                                            >
                                                <Pencil className="h-4 w-4" />
                                            </button>
                                        </TableCell>
                                    </TableRow>
                                    {expandedTenant === tenant.id && (
                                        <TableRow key={`${tenant.id}-detail`}>
                                            <TableCell colSpan={9} className="bg-muted/30">
                                                <div className="py-4 px-6">
                                                    <h4 className="mb-3 text-sm font-medium">
                                                        Pengelola
                                                    </h4>
                                                    {tenant.pengelola_users.length > 0 ? (
                                                        <div className="space-y-2">
                                                            {tenant.pengelola_users.map((user) => (
                                                                <div
                                                                    key={user.id}
                                                                    className="flex items-center gap-3"
                                                                >
                                                                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-sm font-medium text-primary">
                                                                        {user.name
                                                                            .charAt(0)
                                                                            .toUpperCase()}
                                                                    </div>
                                                                    <div>
                                                                        <div className="text-sm font-medium">
                                                                            {user.name}
                                                                        </div>
                                                                        <div className="text-xs text-muted-foreground">
                                                                            {user.email}
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    ) : (
                                                        <p className="text-sm text-muted-foreground">
                                                            Belum ada pengelola
                                                        </p>
                                                    )}
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            {/* Edit Usaha Modal */}
            {editingTenant && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50">
                    <Card className="w-full max-w-md">
                        <CardHeader>
                            <CardTitle>Edit Usaha</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <form onSubmit={handleEdit} className="space-y-4">
                                <div>
                                    <Label htmlFor="edit-name">Nama Usaha *</Label>
                                    <Input
                                        id="edit-name"
                                        value={editForm.data.name}
                                        onChange={(e) => {
                                            editForm.setData('name', e.target.value);
                                            editForm.setData('slug', generateSlug(e.target.value));
                                        }}
                                        placeholder="Nama usaha"
                                        className="mt-1"
                                    />
                                    {editForm.errors.name && (
                                        <p className="mt-1 text-xs text-destructive">
                                            {editForm.errors.name}
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="edit-slug">Slug (URL) *</Label>
                                    <div className="flex items-center gap-2 mt-1">
                                        <span className="text-sm text-muted-foreground whitespace-nowrap">
                                            wolee.my.id/
                                        </span>
                                        <Input
                                            id="edit-slug"
                                            value={editForm.data.slug}
                                            onChange={(e) =>
                                                editForm.setData('slug', e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '').replace(/--+/g, '-'))
                                            }
                                            placeholder="nama-toko"
                                            className="flex-1"
                                        />
                                    </div>
                                    {editForm.errors.slug && (
                                        <p className="mt-1 text-xs text-destructive">
                                            {editForm.errors.slug}
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="edit-business-type">Tipe Usaha *</Label>
                                    <Select
                                        id="edit-business-type"
                                        value={editForm.data.business_type}
                                        onChange={(e) => editForm.setData('business_type', e.target.value)}
                                        className="mt-1"
                                    >
                                        <option value="single">Single Outlet</option>
                                        <option value="multi">Multi Outlet (Cabang)</option>
                                    </Select>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Single = 1 lokasi. Multi = banyak cabang + distribusi stok.
                                    </p>
                                </div>
                                <div className="flex gap-2">
                                    <Button type="submit" disabled={editForm.processing}>
                                        Simpan
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => {
                                            setEditingTenant(null);
                                            editForm.reset();
                                        }}
                                    >
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

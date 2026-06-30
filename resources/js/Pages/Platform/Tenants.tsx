import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Search, ChevronDown, ChevronRight, Plus } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select } from '@/Components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
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

    const addForm = useForm({
        name: '',
        slug: '',
        plan: 'free',
        pengelola_name: '',
        pengelola_email: '',
        pengelola_password: '',
        pengelola_password_confirmation: '',
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

    const generateSlug = (name: string) => {
        return name
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    };

    const filteredTenants = tenants.filter((tenant) => 
        search === '' || 
        tenant.name.toLowerCase().includes(search.toLowerCase()) ||
        tenant.slug.toLowerCase().includes(search.toLowerCase())
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
                                        onChange={(e) => {
                                            addForm.setData('name', e.target.value);
                                            if (!addForm.data.slug) {
                                                addForm.setData('slug', generateSlug(e.target.value));
                                            }
                                        }}
                                        className="mt-1"
                                    />
                                    {addForm.errors.name && (
                                        <p className="mt-1 text-xs text-destructive">{addForm.errors.name}</p>
                                    )}
                                </div>
                                <div>
                                    <Label htmlFor="add-slug">Slug</Label>
                                    <Input
                                        id="add-slug"
                                        value={addForm.data.slug}
                                        onChange={(e) => addForm.setData('slug', e.target.value)}
                                        className="mt-1"
                                    />
                                    {addForm.errors.slug && (
                                        <p className="mt-1 text-xs text-destructive">{addForm.errors.slug}</p>
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

                            <div className="border-t pt-4">
                                <h4 className="mb-3 text-sm font-medium">Pengelola (Owner)</h4>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <Label htmlFor="add-pengelola-name">Nama</Label>
                                        <Input
                                            id="add-pengelola-name"
                                            value={addForm.data.pengelola_name}
                                            onChange={(e) => addForm.setData('pengelola_name', e.target.value)}
                                            className="mt-1"
                                        />
                                        {addForm.errors.pengelola_name && (
                                            <p className="mt-1 text-xs text-destructive">{addForm.errors.pengelola_name}</p>
                                        )}
                                    </div>
                                    <div>
                                        <Label htmlFor="add-pengelola-email">Email</Label>
                                        <Input
                                            id="add-pengelola-email"
                                            type="email"
                                            value={addForm.data.pengelola_email}
                                            onChange={(e) => addForm.setData('pengelola_email', e.target.value)}
                                            className="mt-1"
                                        />
                                        {addForm.errors.pengelola_email && (
                                            <p className="mt-1 text-xs text-destructive">{addForm.errors.pengelola_email}</p>
                                        )}
                                    </div>
                                    <div>
                                        <Label htmlFor="add-pengelola-password">Password</Label>
                                        <Input
                                            id="add-pengelola-password"
                                            type="password"
                                            value={addForm.data.pengelola_password}
                                            onChange={(e) => addForm.setData('pengelola_password', e.target.value)}
                                            className="mt-1"
                                        />
                                        {addForm.errors.pengelola_password && (
                                            <p className="mt-1 text-xs text-destructive">{addForm.errors.pengelola_password}</p>
                                        )}
                                    </div>
                                    <div>
                                        <Label htmlFor="add-pengelola-password-confirm">Konfirmasi Password</Label>
                                        <Input
                                            id="add-pengelola-password-confirm"
                                            type="password"
                                            value={addForm.data.pengelola_password_confirmation}
                                            onChange={(e) => addForm.setData('pengelola_password_confirmation', e.target.value)}
                                            className="mt-1"
                                        />
                                    </div>
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

            {/* Search */}
            <Card className="mb-6">
                <CardContent className="p-4">
                    <div className="relative">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            placeholder="Cari nama atau slug usaha..."
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
                                <TableHead>Users</TableHead>
                                <TableHead>Bot Token</TableHead>
                                <TableHead>AI Hari Ini</TableHead>
                                <TableHead>Feedback</TableHead>
                                <TableHead>Dibuat</TableHead>
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
                                            <div className="text-xs text-muted-foreground">{tenant.slug}</div>
                                        </TableCell>
                                        <TableCell className="uppercase text-xs text-muted-foreground">{tenant.plan}</TableCell>
                                        <TableCell className="uppercase text-xs text-muted-foreground">{tenant.status}</TableCell>
                                        <TableCell>
                                            {formatNumber(tenant.users_count)}
                                            <span className="ml-1 text-xs text-muted-foreground">
                                                ({tenant.pengelola_count} pengelola, {tenant.staff_count} staff)
                                            </span>
                                        </TableCell>
                                        <TableCell>{tenant.has_bot_token ? 'Aktif' : '-'}</TableCell>
                                        <TableCell>{formatNumber(tenant.ai_usage_today)}</TableCell>
                                        <TableCell>{formatNumber(tenant.feedback_count)}</TableCell>
                                        <TableCell className="text-muted-foreground">{formatDate(tenant.created_at)}</TableCell>
                                    </TableRow>
                                    {expandedTenant === tenant.id && (
                                        <TableRow key={`${tenant.id}-detail`}>
                                            <TableCell colSpan={9} className="bg-muted/30">
                                                <div className="py-4 px-6">
                                                    <h4 className="mb-3 text-sm font-medium">Pengelola</h4>
                                                    {tenant.pengelola_users.length > 0 ? (
                                                        <div className="space-y-2">
                                                            {tenant.pengelola_users.map((user) => (
                                                                <div key={user.id} className="flex items-center gap-3">
                                                                    <div className="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10 text-sm font-medium text-primary">
                                                                        {user.name.charAt(0).toUpperCase()}
                                                                    </div>
                                                                    <div>
                                                                        <div className="text-sm font-medium">{user.name}</div>
                                                                        <div className="text-xs text-muted-foreground">{user.email}</div>
                                                                    </div>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    ) : (
                                                        <p className="text-sm text-muted-foreground">Belum ada pengelola</p>
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
        </AppLayout>
    );
}

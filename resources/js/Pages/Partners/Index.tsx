import { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Search, Trash2, Users } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import Modal from '@/Components/ui/modal';
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
import { Badge } from '@/Components/ui/badge';
import { formatRupiah } from '@/lib/format';
import type { PageProps } from '@/types';

interface Partner {
    id: number;
    name: string;
    type: 'customer' | 'supplier';
    contact: string | null;
    phone: string | null;
    email: string | null;
    address: string | null;
    outstanding_count: number;
    total_outstanding: number;
}

interface Props {
    partners: Partner[];
    filters: { type: string; q: string };
}

export default function PartnersIndex({ partners, filters }: Props) {
    const { props } = usePage<PageProps>();
    const isOwner = props.auth.user.role === 'pengelola';
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Partner | null>(null);
    const [search, setSearch] = useState(filters.q ?? '');

    const form = useForm({
        name: '',
        type: 'customer',
        contact: '',
        phone: '',
        email: '',
        address: '',
    });

    const applyFilters = (type: string) => {
        router.get(
            '/partners',
            { type: type || undefined, q: search || undefined },
            { preserveState: true },
        );
    };

    const submitSearch = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters(filters.type);
    };

    const openCreate = () => {
        setEditing(null);
        form.setData({
            name: '',
            type: 'customer',
            contact: '',
            phone: '',
            email: '',
            address: '',
        });
        form.clearErrors();
        setFormOpen(true);
    };

    const openEdit = (partner: Partner) => {
        setEditing(partner);
        form.setData({
            name: partner.name,
            type: partner.type,
            contact: partner.contact ?? '',
            phone: partner.phone ?? '',
            email: partner.email ?? '',
            address: partner.address ?? '',
        });
        form.clearErrors();
        setFormOpen(true);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        if (editing) {
            form.put(`/partners/${editing.id}`, { onSuccess: () => setFormOpen(false) });
        } else {
            form.post('/partners', { onSuccess: () => setFormOpen(false) });
        }
    };

    const remove = (partner: Partner) => {
        if (confirm(`Hapus partner "${partner.name}"?`)) {
            router.delete(`/partners/${partner.id}`);
        }
    };

    return (
        <AppLayout title="Partners">
            <Head title="Partners" />

            <Card>
                <CardHeader className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <CardTitle className="flex items-center gap-2">
                        <Users className="h-5 w-5" />
                        Partners
                    </CardTitle>
                    {isOwner && (
                        <Button onClick={openCreate}>
                            <Plus className="mr-2 h-4 w-4" />
                            Tambah
                        </Button>
                    )}
                </CardHeader>
                <CardContent className="space-y-4">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex gap-2">
                            {[
                                { key: '', label: 'Semua' },
                                { key: 'customer', label: 'Customer' },
                                { key: 'supplier', label: 'Supplier' },
                            ].map((f) => (
                                <Button
                                    key={f.key}
                                    size="sm"
                                    variant={filters.type === f.key ? 'default' : 'outline'}
                                    onClick={() => applyFilters(f.key)}
                                >
                                    {f.label}
                                </Button>
                            ))}
                        </div>
                        <form onSubmit={submitSearch} className="flex gap-2">
                            <Input
                                placeholder="Cari nama..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                className="w-full sm:w-64"
                            />
                            <Button type="submit" variant="outline" size="icon">
                                <Search className="h-4 w-4" />
                            </Button>
                        </form>
                    </div>

                    <div className="overflow-x-auto">
<Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama</TableHead>
                                <TableHead>Tipe</TableHead>
                                <TableHead>Kontak</TableHead>
                                <TableHead>Telepon</TableHead>
                                <TableHead className="text-right">Outstanding</TableHead>
                                {isOwner && <TableHead className="w-24" />}
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {partners.length === 0 ? (
                                <TableRow>
                                    <TableCell
                                        colSpan={isOwner ? 6 : 5}
                                        className="text-center text-muted-foreground"
                                    >
                                        Belum ada partner.
                                    </TableCell>
                                </TableRow>
                            ) : (
                                partners.map((partner) => (
                                    <TableRow
                                        key={partner.id}
                                        className="cursor-pointer hover:bg-muted/50"
                                    >
                                        <TableCell>
                                            <Link
                                                href={`/partners/${partner.id}`}
                                                className="font-medium hover:underline"
                                            >
                                                {partner.name}
                                            </Link>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline">
                                                {partner.type === 'customer'
                                                    ? 'Customer'
                                                    : 'Supplier'}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>{partner.contact ?? '-'}</TableCell>
                                        <TableCell>{partner.phone ?? '-'}</TableCell>
                                        <TableCell className="text-right">
                                            {partner.outstanding_count > 0
                                                ? formatRupiah(partner.total_outstanding)
                                                : '-'}
                                        </TableCell>
                                        {isOwner && (
                                            <TableCell>
                                                <div className="flex gap-1">
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        onClick={() => openEdit(partner)}
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        size="icon"
                                                        variant="ghost"
                                                        onClick={() => remove(partner)}
                                                    >
                                                        <Trash2 className="h-4 w-4 text-destructive" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        )}
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>
</div>
                </CardContent>
            </Card>

            <Modal
                open={formOpen}
                onClose={() => setFormOpen(false)}
                title={editing ? 'Edit Partner' : 'Tambah Partner'}
            >
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <Label htmlFor="name">Nama</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                        />
                        {form.errors.name && (
                            <p className="mt-1 text-xs text-destructive">{form.errors.name}</p>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="type">Tipe</Label>
                        <Select
                            id="type"
                            value={form.data.type}
                            onChange={(e) => form.setData('type', e.target.value)}
                        >
                            <option value="customer">Customer</option>
                            <option value="supplier">Supplier</option>
                        </Select>
                    </div>
                    <div>
                        <Label htmlFor="contact">Kontak</Label>
                        <Input
                            id="contact"
                            value={form.data.contact}
                            onChange={(e) => form.setData('contact', e.target.value)}
                        />
                    </div>
                    <div>
                        <Label htmlFor="phone">Telepon</Label>
                        <Input
                            id="phone"
                            value={form.data.phone}
                            onChange={(e) => form.setData('phone', e.target.value)}
                        />
                    </div>
                    <div>
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                        />
                    </div>
                    <div>
                        <Label htmlFor="address">Alamat</Label>
                        <Input
                            id="address"
                            value={form.data.address}
                            onChange={(e) => form.setData('address', e.target.value)}
                        />
                    </div>
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={() => setFormOpen(false)}>
                            Batal
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            Simpan
                        </Button>
                    </div>
                </form>
            </Modal>
        </AppLayout>
    );
}

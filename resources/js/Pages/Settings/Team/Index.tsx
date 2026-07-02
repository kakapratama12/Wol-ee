import { useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Pencil, Plus } from 'lucide-react';
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

interface Member {
    id: number;
    name: string;
    email: string;
    role: string;
    branch_id: number | null;
    branch_name: string | null;
}

interface BranchOption {
    id: number;
    name: string;
}

interface Props {
    members: Member[];
    branches: BranchOption[];
    roles: Record<string, string>;
}

export default function TeamIndex({ members, branches, roles }: Props) {
    const [showCreate, setShowCreate] = useState(false);
    const [editing, setEditing] = useState<Member | null>(null);

    const createForm = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
        role: 'staff',
        branch_id: '' as string | number,
    });

    const editForm = useForm({
        name: '',
        role: 'staff',
        branch_id: '' as string | number,
    });

    const openEdit = (member: Member) => {
        setEditing(member);
        editForm.setData({
            name: member.name,
            role: member.role,
            branch_id: member.branch_id ?? '',
        });
    };

    const submitCreate = (e: React.FormEvent) => {
        e.preventDefault();
        createForm.post('/settings/team', {
            onSuccess: () => {
                createForm.reset();
                setShowCreate(false);
            },
        });
    };

    const submitEdit = (e: React.FormEvent) => {
        e.preventDefault();
        if (!editing) return;
        editForm.put(`/settings/team/${editing.id}`, {
            onSuccess: () => setEditing(null),
        });
    };

    return (
        <AppLayout title="Tim & Kasir">
            <Head title="Tim & Kasir" />

            <div className="mb-4 flex items-center justify-between">
                <p className="text-sm text-muted-foreground">
                    Tambah staff atau kasir POS. Kasir wajib punya cabang.
                </p>
                <Button type="button" onClick={() => setShowCreate(true)}>
                    <Plus className="mr-2 h-4 w-4" />
                    Tambah Anggota
                </Button>
            </div>

            <Card>
                <CardHeader>
                    <CardTitle>Anggota Tim</CardTitle>
                </CardHeader>
                <CardContent>
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Nama</TableHead>
                                <TableHead>Email</TableHead>
                                <TableHead>Peran</TableHead>
                                <TableHead>Cabang</TableHead>
                                <TableHead className="w-16" />
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {members.map((member) => (
                                <TableRow key={member.id}>
                                    <TableCell className="font-medium">{member.name}</TableCell>
                                    <TableCell>{member.email}</TableCell>
                                    <TableCell>{roles[member.role] ?? member.role}</TableCell>
                                    <TableCell>{member.branch_name ?? '—'}</TableCell>
                                    <TableCell>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => openEdit(member)}
                                        >
                                            <Pencil className="h-4 w-4" />
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </CardContent>
            </Card>

            <Modal open={showCreate} onClose={() => setShowCreate(false)} title="Tambah Anggota">
                <form onSubmit={submitCreate} className="space-y-4">
                    <div>
                        <Label>Nama</Label>
                        <Input
                            value={createForm.data.name}
                            onChange={(e) => createForm.setData('name', e.target.value)}
                        />
                    </div>
                    <div>
                        <Label>Email</Label>
                        <Input
                            type="email"
                            value={createForm.data.email}
                            onChange={(e) => createForm.setData('email', e.target.value)}
                        />
                    </div>
                    <div>
                        <Label>Password</Label>
                        <Input
                            type="password"
                            value={createForm.data.password}
                            onChange={(e) => createForm.setData('password', e.target.value)}
                        />
                    </div>
                    <div>
                        <Label>Konfirmasi Password</Label>
                        <Input
                            type="password"
                            value={createForm.data.password_confirmation}
                            onChange={(e) =>
                                createForm.setData('password_confirmation', e.target.value)
                            }
                        />
                    </div>
                    <div>
                        <Label>Peran</Label>
                        <Select
                            value={createForm.data.role}
                            onChange={(e) => createForm.setData('role', e.target.value)}
                        >
                            {Object.entries(roles).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </Select>
                    </div>
                    {createForm.data.role === 'cashier' && (
                        <div>
                            <Label>Cabang</Label>
                            <Select
                                value={String(createForm.data.branch_id)}
                                onChange={(e) => createForm.setData('branch_id', e.target.value)}
                            >
                                <option value="">Pilih cabang</option>
                                {branches.map((b) => (
                                    <option key={b.id} value={b.id}>
                                        {b.name}
                                    </option>
                                ))}
                            </Select>
                        </div>
                    )}
                    <Button type="submit" disabled={createForm.processing}>
                        Simpan
                    </Button>
                </form>
            </Modal>

            <Modal open={!!editing} onClose={() => setEditing(null)} title="Edit Anggota">
                <form onSubmit={submitEdit} className="space-y-4">
                    <div>
                        <Label>Nama</Label>
                        <Input
                            value={editForm.data.name}
                            onChange={(e) => editForm.setData('name', e.target.value)}
                        />
                    </div>
                    <div>
                        <Label>Peran</Label>
                        <Select
                            value={editForm.data.role}
                            onChange={(e) => editForm.setData('role', e.target.value)}
                        >
                            {Object.entries(roles).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </Select>
                    </div>
                    {editForm.data.role === 'cashier' && (
                        <div>
                            <Label>Cabang</Label>
                            <Select
                                value={String(editForm.data.branch_id)}
                                onChange={(e) => editForm.setData('branch_id', e.target.value)}
                            >
                                <option value="">Pilih cabang</option>
                                {branches.map((b) => (
                                    <option key={b.id} value={b.id}>
                                        {b.name}
                                    </option>
                                ))}
                            </Select>
                        </div>
                    )}
                    <Button type="submit" disabled={editForm.processing}>
                        Simpan
                    </Button>
                </form>
            </Modal>
        </AppLayout>
    );
}

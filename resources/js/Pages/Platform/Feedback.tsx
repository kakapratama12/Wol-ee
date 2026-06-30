import { FormEvent, useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import Pagination from '@/Components/Pagination';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
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
import type { Paginated } from '@/types';

interface FeedbackRow {
    id: number;
    tenant_id: number;
    tenant: string | null;
    telegram_user_id: number;
    original_message: string | null;
    feedback_text: string;
    status: string;
    note: string | null;
    created_at: string | null;
}

interface TenantOption {
    id: number;
    name: string;
}

interface Props {
    feedback: Paginated<FeedbackRow>;
    filters: {
        status: string;
        tenant_id: number | null;
    };
    tenants: TenantOption[];
    statuses: string[];
}

export default function Feedback({ feedback, filters, tenants, statuses }: Props) {
    const [editing, setEditing] = useState<FeedbackRow | null>(null);
    const filterForm = useForm({
        status: filters.status ?? '',
        tenant_id: filters.tenant_id ? String(filters.tenant_id) : '',
    });
    const editForm = useForm({ status: '', note: '' });

    const submitFilters = (e: FormEvent) => {
        e.preventDefault();
        router.get('/platform/feedback', filterForm.data, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const openEdit = (row: FeedbackRow) => {
        setEditing(row);
        editForm.setData({ status: row.status, note: row.note ?? '' });
        editForm.clearErrors();
    };

    const submitEdit = (e: FormEvent) => {
        e.preventDefault();
        if (!editing) return;
        editForm.put(`/platform/feedback/${editing.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditing(null),
        });
    };

    return (
        <AppLayout title="Feedback Inbox">
            <Head title="Platform Feedback" />

            <Card>
                <CardHeader>
                    <CardTitle>Feedback Inbox</CardTitle>
                </CardHeader>
                <CardContent>
                    <form
                        onSubmit={submitFilters}
                        className="mb-4 grid gap-3 md:grid-cols-[1fr_1fr_auto]"
                    >
                        <Select
                            value={filterForm.data.status}
                            onChange={(e) => filterForm.setData('status', e.target.value)}
                        >
                            <option value="">Semua status</option>
                            {statuses.map((status) => (
                                <option key={status} value={status}>
                                    {status}
                                </option>
                            ))}
                        </Select>
                        <Select
                            value={filterForm.data.tenant_id}
                            onChange={(e) => filterForm.setData('tenant_id', e.target.value)}
                        >
                            <option value="">Semua tenant</option>
                            {tenants.map((tenant) => (
                                <option key={tenant.id} value={tenant.id}>
                                    {tenant.name}
                                </option>
                            ))}
                        </Select>
                        <Button type="submit">Filter</Button>
                    </form>

                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Tanggal</TableHead>
                                <TableHead>Tenant</TableHead>
                                <TableHead>Feedback</TableHead>
                                <TableHead>Original</TableHead>
                                <TableHead>Status</TableHead>
                                <TableHead>Aksi</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {feedback.data.map((row) => (
                                <TableRow key={row.id}>
                                    <TableCell className="text-muted-foreground">
                                        {formatDate(row.created_at)}
                                    </TableCell>
                                    <TableCell>
                                        <div>{row.tenant ?? '-'}</div>
                                        <div className="text-xs text-muted-foreground">
                                            TG {row.telegram_user_id}
                                        </div>
                                    </TableCell>
                                    <TableCell className="max-w-sm whitespace-pre-wrap">
                                        {row.feedback_text}
                                    </TableCell>
                                    <TableCell className="max-w-xs whitespace-pre-wrap text-muted-foreground">
                                        {row.original_message ?? '-'}
                                    </TableCell>
                                    <TableCell className="uppercase text-xs text-muted-foreground">
                                        {row.status}
                                    </TableCell>
                                    <TableCell>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => openEdit(row)}
                                        >
                                            Review
                                        </Button>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <Pagination links={feedback.links} />
                </CardContent>
            </Card>

            {editing && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
                    <div className="w-full max-w-lg rounded-lg bg-background p-6 shadow-lg">
                        <h2 className="text-lg font-semibold">Review Feedback</h2>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {editing.feedback_text}
                        </p>
                        <form onSubmit={submitEdit} className="mt-4 space-y-4">
                            <Select
                                value={editForm.data.status}
                                onChange={(e) => editForm.setData('status', e.target.value)}
                            >
                                {statuses.map((status) => (
                                    <option key={status} value={status}>
                                        {status}
                                    </option>
                                ))}
                            </Select>
                            <Input
                                value={editForm.data.note}
                                onChange={(e) => editForm.setData('note', e.target.value)}
                                placeholder="Catatan kurasi (opsional)"
                            />
                            <div className="flex justify-end gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setEditing(null)}
                                >
                                    Batal
                                </Button>
                                <Button type="submit" disabled={editForm.processing}>
                                    Simpan
                                </Button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}

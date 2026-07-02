import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowLeft, Eye, Plus, Search } from 'lucide-react';

interface Payable {
    id: number;
    payable_number: string;
    partner: { id: number; name: string };
    amount: number;
    paid_amount: number;
    due_date: string | null;
    status: string;
    created_at: string;
}

interface PaginatedPayables {
    data: Payable[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}

function formatRupiah(amount: number): string {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    }).format(amount);
}

function formatDate(date: string | null): string {
    if (!date) return '-';
    return new Date(date).toLocaleDateString('id-ID', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

function StatusBadge({ status }: { status: string }) {
    const styles: Record<string, string> = {
        outstanding: 'bg-yellow-100 text-yellow-800',
        partial: 'bg-blue-100 text-blue-800',
        paid: 'bg-green-100 text-green-800',
        draft: 'bg-gray-100 text-gray-600',
    };

    const labels: Record<string, string> = {
        outstanding: 'Belum Lunas',
        partial: 'Sebagian',
        paid: 'Lunas',
        draft: 'Draft',
    };

    return (
        <span className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${styles[status] || styles.draft}`}>
            {labels[status] || status}
        </span>
    );
}

export default function Index({
    payables,
    filters,
}: {
    payables: PaginatedPayables;
    filters: { status?: string };
}) {
    const [statusFilter, setStatusFilter] = useState(filters.status || '');

    const handleFilter = (status: string) => {
        setStatusFilter(status);
        router.get('/payables', status ? { status } : {}, { preserveState: true });
    };

    return (
        <AppLayout title="Tagihan Supplier">
            <Head title="Tagihan Supplier" />

            <div className="mb-4">
                <Link
                    href="/dashboard"
                    className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Kembali
                </Link>
            </div>

            {/* Filter */}
            <div className="mb-4 flex flex-wrap gap-2">
                {[
                    { value: '', label: 'Semua' },
                    { value: 'outstanding', label: 'Belum Lunas' },
                    { value: 'partial', label: 'Sebagian' },
                    { value: 'paid', label: 'Lunas' },
                ].map((item) => (
                    <button
                        key={item.value}
                        onClick={() => handleFilter(item.value)}
                        className={`rounded-lg px-3 py-1.5 text-sm font-medium transition-colors ${
                            statusFilter === item.value
                                ? 'bg-primary text-primary-foreground'
                                : 'bg-card border border-border text-muted-foreground hover:bg-accent'
                        }`}
                    >
                        {item.label}
                    </button>
                ))}
            </div>

            {/* Table */}
            <div className="rounded-lg border border-border bg-card">
                <div className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead>
                            <tr className="border-b border-border">
                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Nomor</th>
                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Supplier</th>
                                <th className="px-4 py-3 text-right font-medium text-muted-foreground">Jumlah</th>
                                <th className="px-4 py-3 text-right font-medium text-muted-foreground">Dibayar</th>
                                <th className="px-4 py-3 text-right font-medium text-muted-foreground">Sisa</th>
                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Jatuh Tempo</th>
                                <th className="px-4 py-3 text-left font-medium text-muted-foreground">Status</th>
                                <th className="px-4 py-3 text-center font-medium text-muted-foreground">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            {payables.data.length === 0 ? (
                                <tr>
                                    <td colSpan={8} className="px-4 py-8 text-center text-muted-foreground">
                                        Belum ada tagihan supplier.
                                    </td>
                                </tr>
                            ) : (
                                payables.data.map((payable) => {
                                    const remaining = Math.max(0, payable.amount - payable.paid_amount);
                                    return (
                                        <tr key={payable.id} className="border-b border-border last:border-0 hover:bg-muted/50">
                                            <td className="px-4 py-3 font-medium">{payable.payable_number}</td>
                                            <td className="px-4 py-3">{payable.partner.name}</td>
                                            <td className="px-4 py-3 text-right">{formatRupiah(payable.amount)}</td>
                                            <td className="px-4 py-3 text-right">{formatRupiah(payable.paid_amount)}</td>
                                            <td className="px-4 py-3 text-right font-medium">{formatRupiah(remaining)}</td>
                                            <td className="px-4 py-3">{formatDate(payable.due_date)}</td>
                                            <td className="px-4 py-3"><StatusBadge status={payable.status} /></td>
                                            <td className="px-4 py-3 text-center">
                                                <Link
                                                    href={`/payables/${payable.id}`}
                                                    className="inline-flex items-center gap-1 rounded-md px-2 py-1 text-muted-foreground hover:bg-accent hover:text-foreground"
                                                >
                                                    <Eye className="h-4 w-4" />
                                                </Link>
                                            </td>
                                        </tr>
                                    );
                                })
                            )}
                        </tbody>
                    </table>
                </div>

                {/* Pagination */}
                {payables.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-border px-4 py-3">
                        <p className="text-sm text-muted-foreground">
                            Menampilkan {payables.data.length} dari {payables.total} tagihan
                        </p>
                        <div className="flex gap-1">
                            {Array.from({ length: payables.last_page }, (_, i) => i + 1).map((page) => (
                                <button
                                    key={page}
                                    onClick={() => router.get('/payables', { ...filters, page })}
                                    className={`rounded px-3 py-1 text-sm ${
                                        page === payables.current_page
                                            ? 'bg-primary text-primary-foreground'
                                            : 'hover:bg-accent'
                                    }`}
                                >
                                    {page}
                                </button>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}

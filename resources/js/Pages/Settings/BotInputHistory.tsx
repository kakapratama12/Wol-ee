import { useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { Archive, Bot, Clock, Filter } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import type { PageProps } from '@/types';

interface BotInput {
    id: number;
    entity_type: string;
    entity_id: number | null;
    summary: string;
    raw_input: string;
    parsed_data: Record<string, unknown>;
    status: string;
    created_at: string;
}

interface Props {
    inputs: BotInput[];
    filters: {
        entity_type: string;
        status: string;
    };
}

const ENTITY_LABELS: Record<string, string> = {
    product: 'Produk',
    ingredient: 'Bahan',
    recipe: 'Resep',
    transaction: 'Pembelian',
    sale: 'Penjualan',
    invoice: 'Invoice',
    partner: 'Mitra',
    expense: 'Pengeluaran',
};

const ENTITY_COLORS: Record<string, string> = {
    product: 'bg-blue-100 text-blue-800',
    ingredient: 'bg-green-100 text-green-800',
    recipe: 'bg-purple-100 text-purple-800',
    transaction: 'bg-orange-100 text-orange-800',
    sale: 'bg-emerald-100 text-emerald-800',
    invoice: 'bg-yellow-100 text-yellow-800',
    partner: 'bg-cyan-100 text-cyan-800',
    expense: 'bg-red-100 text-red-800',
};

export default function BotInputHistory({ inputs, filters }: Props) {
    const { props } = usePage<PageProps>();
    const [entityFilter, setEntityFilter] = useState(filters.entity_type);

    const applyFilter = (type: string) => {
        setEntityFilter(type);
        router.get(
            '/settings/bot/history',
            { entity_type: type || undefined, status: filters.status },
            { preserveState: true },
        );
    };

    const archive = (id: number) => {
        if (confirm('Arsipkan input ini?')) {
            router.put(`/settings/bot/history/${id}/archive`, {}, { preserveState: true });
        }
    };

    return (
        <AppLayout>
            <Head title="Riwayat Input Bot" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="mb-6">
                        <h1 className="text-2xl font-bold text-gray-900 flex items-center gap-2">
                            <Bot className="w-6 h-6" />
                            Riwayat Input Bot
                        </h1>
                        <p className="text-sm text-gray-500 mt-1">
                            Semua data yang di-input melalui Telegram bot
                        </p>
                    </div>

                    {/* Filters */}
                    <div className="mb-6 flex flex-wrap gap-2">
                        <Button
                            variant={entityFilter === '' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => applyFilter('')}
                        >
                            Semua
                        </Button>
                        {Object.entries(ENTITY_LABELS).map(([key, label]) => (
                            <Button
                                key={key}
                                variant={entityFilter === key ? 'default' : 'outline'}
                                size="sm"
                                onClick={() => applyFilter(key)}
                            >
                                {label}
                            </Button>
                        ))}
                    </div>

                    {/* Table */}
                    <div className="bg-white shadow rounded-lg overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Waktu
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Tipe
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Ringkasan
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Input User
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {inputs.map((input) => (
                                        <tr key={input.id} className="hover:bg-gray-50">
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <div className="flex items-center gap-1">
                                                    <Clock className="w-4 h-4" />
                                                    {input.created_at}
                                                </div>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <Badge className={ENTITY_COLORS[input.entity_type] ?? 'bg-gray-100 text-gray-800'}>
                                                    {ENTITY_LABELS[input.entity_type] ?? input.entity_type}
                                                </Badge>
                                            </td>
                                            <td className="px-6 py-4 text-sm text-gray-900">
                                                {input.summary}
                                            </td>
                                            <td className="px-6 py-4 text-sm text-gray-500 max-w-xs truncate">
                                                "{input.raw_input}"
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <Badge variant={input.status === 'active' ? 'default' : 'secondary'}>
                                                    {input.status === 'active' ? 'Aktif' : 'Diarsipkan'}
                                                </Badge>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                {input.status === 'active' && (
                                                    <Button
                                                        variant="ghost"
                                                        size="sm"
                                                        onClick={() => archive(input.id)}
                                                        className="text-gray-500 hover:text-gray-700"
                                                    >
                                                        <Archive className="w-4 h-4" />
                                                    </Button>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                    {inputs.length === 0 && (
                                        <tr>
                                            <td colSpan={6} className="px-6 py-12 text-center text-gray-500">
                                                Belum ada input dari bot
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}

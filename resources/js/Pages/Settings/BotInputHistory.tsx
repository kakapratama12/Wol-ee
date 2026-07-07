import { useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Archive, Bot, Check, Clock, ExternalLink, Pencil, AlertTriangle } from 'lucide-react';
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
    edit_url: string | null;
    completeness: 'complete' | 'incomplete' | 'deleted';
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
    product: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
    ingredient: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
    recipe: 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
    transaction: 'bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200',
    sale: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200',
    invoice: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
    partner: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900 dark:text-cyan-200',
    expense: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
};

const COMPLETENESS_CONFIG = {
    complete: { label: 'Lengkap', icon: Check, color: 'text-green-600 dark:text-green-400', bg: 'bg-green-50 dark:bg-green-900/30' },
    incomplete: { label: 'Belum lengkap', icon: AlertTriangle, color: 'text-amber-600 dark:text-amber-400', bg: 'bg-amber-50 dark:bg-amber-900/30' },
    deleted: { label: 'Dihapus', icon: AlertTriangle, color: 'text-red-600 dark:text-red-400', bg: 'bg-red-50 dark:bg-red-900/30' },
};

export default function BotInputHistory({ inputs, filters }: Props) {
    const { props } = usePage<PageProps>();
    const [entityFilter, setEntityFilter] = useState(filters.entity_type);
    const [completenessFilter, setCompletenessFilter] = useState<string>('');

    const applyFilter = (type: string) => {
        setEntityFilter(type);
        router.get(
            '/settings/bot/history',
            { entity_type: type || undefined, status: filters.status, completeness: completenessFilter || undefined },
            { preserveState: true },
        );
    };

    const applyCompletenessFilter = (value: string) => {
        setCompletenessFilter(value);
        router.get(
            '/settings/bot/history',
            { entity_type: entityFilter || undefined, status: filters.status, completeness: value || undefined },
            { preserveState: true },
        );
    };

    const archive = (id: number) => {
        if (confirm('Arsipkan input ini?')) {
            router.put(`/settings/bot/history/${id}/archive`, {}, { preserveState: true });
        }
    };

    // Filter by completeness client-side (since we compute it)
    const filteredInputs = completenessFilter
        ? inputs.filter(i => i.completeness === completenessFilter)
        : inputs;

    return (
        <AppLayout>
            <Head title="Riwayat Input Bot" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <div className="mb-6">
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <Bot className="w-6 h-6" />
                            Riwayat Input Bot
                        </h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            Semua data yang di-input melalui Telegram bot
                        </p>
                    </div>

                    {/* Entity Type Filters */}
                    <div className="mb-4 flex flex-wrap gap-2">
                        <Button
                            variant={entityFilter === '' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => applyFilter('')}
                        >
                            Semua Tipe
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

                    {/* Completeness Filters */}
                    <div className="mb-6 flex flex-wrap gap-2">
                        <Button
                            variant={completenessFilter === '' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => applyCompletenessFilter('')}
                        >
                            Semua Status
                        </Button>
                        <Button
                            variant={completenessFilter === 'incomplete' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => applyCompletenessFilter('incomplete')}
                            className="text-amber-600 dark:text-amber-400"
                        >
                            <AlertTriangle className="w-4 h-4 mr-1" />
                            Belum Lengkap
                        </Button>
                        <Button
                            variant={completenessFilter === 'complete' ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => applyCompletenessFilter('complete')}
                            className="text-green-600 dark:text-green-400"
                        >
                            <Check className="w-4 h-4 mr-1" />
                            Lengkap
                        </Button>
                    </div>

                    {/* Table */}
                    <div className="bg-white dark:bg-gray-900 shadow rounded-lg overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead className="bg-gray-50 dark:bg-gray-800">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Waktu
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Tipe
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Ringkasan
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Input User
                                        </th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Aksi
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                    {filteredInputs.map((input) => {
                                        const completeness = COMPLETENESS_CONFIG[input.completeness];
                                        const CompletenessIcon = completeness.icon;

                                        return (
                                            <tr key={input.id} className="hover:bg-gray-50 dark:hover:bg-gray-800">
                                                <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <div className="flex items-center gap-1">
                                                        <Clock className="w-4 h-4" />
                                                        {input.created_at}
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <Badge className={ENTITY_COLORS[input.entity_type] ?? 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200'}>
                                                        {ENTITY_LABELS[input.entity_type] ?? input.entity_type}
                                                    </Badge>
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-900 dark:text-gray-100 font-medium">
                                                    {input.summary}
                                                </td>
                                                <td className="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate">
                                                    &ldquo;{input.raw_input}&rdquo;
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap">
                                                    <div className={`flex items-center gap-1 ${completeness.color}`}>
                                                        <CompletenessIcon className="w-4 h-4" />
                                                        <span className="text-sm font-medium">{completeness.label}</span>
                                                    </div>
                                                </td>
                                                <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <div className="flex items-center justify-end gap-2">
                                                        {input.edit_url && input.entity_id && (
                                                            <Link href={input.edit_url}>
                                                                <Button variant="ghost" size="sm" className="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                                                    <Pencil className="w-4 h-4 mr-1" />
                                                                    Edit
                                                                </Button>
                                                            </Link>
                                                        )}
                                                        {input.status === 'active' && (
                                                            <Button
                                                                variant="ghost"
                                                                size="sm"
                                                                onClick={() => archive(input.id)}
                                                                className="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                                                            >
                                                                <Archive className="w-4 h-4" />
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                    {filteredInputs.length === 0 && (
                                        <tr>
                                            <td colSpan={6} className="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                                {completenessFilter ? 'Tidak ada data dengan status ini' : 'Belum ada input dari bot'}
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

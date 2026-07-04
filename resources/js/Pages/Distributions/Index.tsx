import { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import Modal from '@/Components/ui/modal';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { formatDate } from '@/lib/format';
import DistributionForm from '@/Components/DistributionForm';

interface Outlet {
    id: number;
    name: string;
    type: string;
}

interface Product {
    id: number;
    name: string;
    unit: string;
}

interface DistributionItem {
    id: number;
    product_id: number | null;
    ingredient_id: number | null;
    product: { name: string } | null;
    ingredient: { name: string } | null;
    quantity: number;
    unit: string;
}

interface Distribution {
    id: number;
    from_outlet: Outlet | null;
    to_outlet: Outlet | null;
    items: DistributionItem[];
    notes: string | null;
    distributed_at: string;
}

interface Ingredient {
    id: number;
    name: string;
    base_unit: string;
    item_type: string;
    current_stock: number;
}

interface Props {
    distributions: Distribution[];
    outlets: Outlet[];
    products: Product[];
    ingredients: Ingredient[];
}

export default function DistributionsIndex({ distributions, outlets, products, ingredients }: Props) {
    const [showCreateModal, setShowCreateModal] = useState(false);

    const now = new Date();
    const pad = (n: number) => String(n).padStart(2, '0');
    const defaultDate = `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;

    const form = useForm({
        from_outlet_id: '',
        to_outlet_id: '',
        distributed_at: defaultDate,
        notes: '',
        items: [{ item_id: '', item_source: '', quantity: '', unit: 'gram' }] as Array<{
            item_id: string;
            item_source: string;
            quantity: string;
            unit: string;
        }>,
    });

    const handleSubmit = () => {
        form.post(route('distributions.store'), {
            onSuccess: () => {
                form.reset();
                setShowCreateModal(false);
            },
        });
    };

    return (
        <AppLayout title="Distribusi">
            <Head title="Distribusi" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Distribusi</h1>
                        <p className="text-sm text-gray-500 dark:text-gray-400">
                            Kirim produk dari pusat ke outlet
                        </p>
                    </div>
                    <Button onClick={() => setShowCreateModal(true)}>
                        <Plus className="mr-2 h-4 w-4" />
                        Distribusi Baru
                    </Button>
                </div>

                <Card>
                    <CardContent className="p-0">
                        <div className="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Tanggal</TableHead>
                                    <TableHead>Dari</TableHead>
                                    <TableHead>Ke</TableHead>
                                    <TableHead>Item</TableHead>
                                    <TableHead>Catatan</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {distributions.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={5} className="text-center text-gray-500 dark:text-gray-400 py-8">
                                            Belum ada distribusi
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    distributions.map((dist) => (
                                        <TableRow key={dist.id} className="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800" onClick={() => router.visit(route('distributions.show', dist.id))}>
                                            <TableCell>{formatDate(dist.distributed_at)}</TableCell>
                                            <TableCell>{dist.from_outlet?.name || 'Gudang Pusat'}</TableCell>
                                            <TableCell>{dist.to_outlet?.name || '-'}</TableCell>
                                            <TableCell>
                                                {dist.items.map((item) => (
                                                    <div key={item.id} className="text-sm">
                                                        {item.product?.name || item.ingredient?.name || '-'}: {Number(item.quantity)} {item.unit}
                                                    </div>
                                                ))}
                                            </TableCell>
                                            <TableCell className="text-gray-500 dark:text-gray-400">
                                                {dist.notes || '-'}
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                        </div>
                    </CardContent>
                </Card>
            </div>

                        {/* Create Modal */}
            <Modal open={showCreateModal} onClose={() => setShowCreateModal(false)} title="Distribusi Baru" size="lg">
                <div className="max-h-[60vh]">
                    <DistributionForm
                        form={form}
                        outlets={outlets}
                        products={products}
                        ingredients={ingredients}
                        onSubmit={handleSubmit}
                        submitLabel="Simpan Distribusi"
                        processing={form.processing}
                    />
                </div>
            </Modal>
        </AppLayout>
    );
}
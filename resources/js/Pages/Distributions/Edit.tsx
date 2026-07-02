import { Head, Link, useForm, router } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
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

interface Ingredient {
    id: number;
    name: string;
    base_unit: string;
    item_type: string;
    current_stock: number;
}

interface DistributionItem {
    id: number;
    product_id: number | null;
    ingredient_id: number | null;
    quantity: number;
    unit: string;
}

interface Distribution {
    id: number;
    from_outlet_id: number | null;
    to_outlet_id: number;
    distributed_at: string;
    notes: string | null;
    items: DistributionItem[];
}

interface Props {
    distribution: Distribution;
    outlets: Outlet[];
    products: Product[];
    ingredients: Ingredient[];
}

export default function DistributionEdit({ distribution, outlets, products, ingredients }: Props) {
    const formatDateForInput = (dateStr: string) => {
        const date = new Date(dateStr);
        const pad = (n: number) => String(n).padStart(2, '0');
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    };

    const form = useForm({
        from_outlet_id: distribution.from_outlet_id?.toString() || '',
        to_outlet_id: distribution.to_outlet_id.toString(),
        distributed_at: formatDateForInput(distribution.distributed_at),
        notes: distribution.notes || '',
        items: distribution.items.map(item => ({
            item_id: item.product_id?.toString() || item.ingredient_id?.toString() || '',
            item_source: item.product_id ? 'product' : 'ingredient',
            quantity: Number(item.quantity).toString(),
            unit: item.unit,
        })) as Array<{
            item_id: string;
            item_source: string;
            quantity: string;
            unit: string;
        }>,
    });

    const handleSubmit = () => {
        router.put(route('distributions.update', distribution.id), {
            from_outlet_id: form.data.from_outlet_id,
            to_outlet_id: form.data.to_outlet_id,
            distributed_at: form.data.distributed_at,
            notes: form.data.notes,
            items: form.data.items,
        }, {
            onSuccess: () => {
                // Redirect handled by controller
            },
        });
    };

    return (
        <AppLayout title="Edit Distribusi">
            <Head title="Edit Distribusi" />

            <div className="space-y-6">
                {/* Back button */}
                <div>
                    <Link
                        href={route('distributions.index')}
                        className="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                    >
                        <ArrowLeft className="mr-1 h-4 w-4" />
                        Kembali ke Daftar Distribusi
                    </Link>
                </div>

                {/* Title */}
                <div>
                    <h1 className="text-2xl font-bold text-gray-900 dark:text-white">Edit Distribusi</h1>
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Ubah data distribusi #{distribution.id}
                    </p>
                </div>

                <Card>
                    <CardContent className="p-6">
                        <DistributionForm
                            form={form}
                            outlets={outlets}
                            products={products}
                            ingredients={ingredients}
                            onSubmit={handleSubmit}
                            submitLabel="Simpan Perubahan"
                            processing={form.processing}
                        />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

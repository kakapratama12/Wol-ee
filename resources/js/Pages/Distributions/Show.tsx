import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, Pencil, Trash2 } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { formatDate } from '@/lib/format';

interface Outlet {
    id: number;
    name: string;
    type: string;
}

interface DistributionItem {
    id: number;
    product: { name: string; unit: string } | null;
    ingredient: { name: string; base_unit: string } | null;
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
    creator: { name: string } | null;
}

interface Props {
    distribution: Distribution;
}

export default function Show({ distribution }: Props) {
    const handleDelete = () => {
        if (
            window.confirm(
                'Yakin ingin menghapus distribusi ini? Stok akan dikembalikan.'
            )
        ) {
            router.delete(route('distributions.destroy', distribution.id));
        }
    };

    return (
        <AppLayout title="Detail Distribusi">
            <Head title="Detail Distribusi" />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <Link href={route('distributions.index')}>
                        <Button variant="ghost" size="sm">
                            <ArrowLeft className="mr-2 h-4 w-4" />
                            Kembali
                        </Button>
                    </Link>
                </div>

                <div className="flex items-center justify-between">
                    <h1 className="text-2xl font-bold">Detail Distribusi</h1>
                    <div className="flex items-center gap-2">
                        <Link href={route('distributions.edit', distribution.id)}>
                            <Button variant="outline" size="sm">
                                <Pencil className="mr-2 h-4 w-4" />
                                Edit
                            </Button>
                        </Link>
                        <Button variant="destructive" size="sm" onClick={handleDelete}>
                            <Trash2 className="mr-2 h-4 w-4" />
                            Hapus
                        </Button>
                    </div>
                </div>

                <Card>
                    <CardContent className="pt-6">
                        <dl className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <dt className="text-sm font-medium text-muted-foreground">
                                    Dari Outlet
                                </dt>
                                <dd className="mt-1 text-sm">
                                    {distribution.from_outlet?.name ?? '-'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-muted-foreground">
                                    Ke Outlet
                                </dt>
                                <dd className="mt-1 text-sm">
                                    {distribution.to_outlet?.name ?? '-'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-muted-foreground">
                                    Tanggal
                                </dt>
                                <dd className="mt-1 text-sm">
                                    {formatDate(distribution.distributed_at)}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-sm font-medium text-muted-foreground">
                                    Dibuat oleh
                                </dt>
                                <dd className="mt-1 text-sm">
                                    {distribution.creator?.name ?? '-'}
                                </dd>
                            </div>
                            <div className="sm:col-span-2">
                                <dt className="text-sm font-medium text-muted-foreground">
                                    Catatan
                                </dt>
                                <dd className="mt-1 text-sm">
                                    {distribution.notes || '-'}
                                </dd>
                            </div>
                        </dl>
                    </CardContent>
                </Card>

                <Card>
                    <CardContent className="pt-6">
                        <h2 className="mb-4 text-lg font-semibold">Item Distribusi</h2>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-[50px]">No</TableHead>
                                    <TableHead>Item</TableHead>
                                    <TableHead>Tipe</TableHead>
                                    <TableHead className="text-right">Jumlah</TableHead>
                                    <TableHead className="text-right">Satuan</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {distribution.items.map((item, index) => (
                                    <TableRow key={item.id}>
                                        <TableCell>{index + 1}</TableCell>
                                        <TableCell>
                                            {item.product?.name || item.ingredient?.name}
                                        </TableCell>
                                        <TableCell>
                                            {item.product ? 'Produk' : 'Bahan Baku'}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            {Number(item.quantity)}
                                        </TableCell>
                                        <TableCell className="text-right">{item.unit}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

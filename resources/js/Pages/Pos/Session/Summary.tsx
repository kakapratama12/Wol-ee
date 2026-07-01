import { Head, Link } from '@inertiajs/react';
import PosLayout from '@/Layouts/PosLayout';

interface ProductSummary {
    product_id: number;
    name: string;
    recipe_type: string;
    max_portions: number;
    bucket: string;
}

interface Props {
    ready: ProductSummary[];
    attention: ProductSummary[];
}

function ProductRow({ item }: { item: ProductSummary }) {
    const label =
        item.recipe_type === 'batch'
            ? `Stok ~${item.max_portions} pcs`
            : `Bahan cukup ~${item.max_portions} porsi`;

    return (
        <div className="flex items-center justify-between gap-3 rounded-lg border border-border px-3 py-2 text-sm">
            <span className="font-medium">{item.name}</span>
            <span className="text-muted-foreground">{label}</span>
        </div>
    );
}

export default function SessionSummary({ ready, attention }: Props) {
    return (
        <PosLayout title="Ringkasan Stok">
            <Head title="Ringkasan Produk" />

            <p className="mb-4 text-sm text-muted-foreground">
                Estimasi per produk. Stok bisa berubah saat jualan berlangsung.
            </p>

            {attention.length > 0 && (
                <section className="mb-6">
                    <h2 className="mb-2 text-sm font-semibold text-destructive">Perlu perhatian</h2>
                    <div className="space-y-2">
                        {attention.map((item) => (
                            <ProductRow key={item.product_id} item={item} />
                        ))}
                    </div>
                </section>
            )}

            {ready.length > 0 && (
                <section className="mb-6">
                    <h2 className="mb-2 text-sm font-semibold text-teal-700 dark:text-teal-400">Siap jual</h2>
                    <div className="space-y-2">
                        {ready.map((item) => (
                            <ProductRow key={item.product_id} item={item} />
                        ))}
                    </div>
                </section>
            )}

            <div className="flex flex-col gap-3 sm:flex-row">
                <Link
                    href="/pos/register"
                    className="inline-flex h-12 flex-1 items-center justify-center rounded-md bg-primary px-4 text-base font-medium text-primary-foreground hover:bg-primary/90"
                >
                    Mulai Jualan
                </Link>
                <Link
                    href="/pos/session/summary/skip"
                    method="post"
                    as="button"
                    className="inline-flex h-12 flex-1 items-center justify-center rounded-md border border-border bg-background px-4 text-base font-medium hover:bg-accent"
                >
                    Lewati
                </Link>
            </div>
        </PosLayout>
    );
}

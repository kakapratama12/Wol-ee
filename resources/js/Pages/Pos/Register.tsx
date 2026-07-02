import { useEffect, useMemo, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { Minus, Plus, Search, Trash2 } from 'lucide-react';
import PosLayout from '@/Layouts/PosLayout';
import PaymentModal from '@/Pages/Pos/components/PaymentModal';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { formatRupiah } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { PageProps } from '@/types';

export interface PosProduct {
    id: number;
    name: string;
    selling_price: number;
    recipe_type: string;
    max_portions: number;
    disabled: boolean;
}

interface CartLine {
    product_id: number;
    name: string;
    unit_price: number;
    quantity: number;
}

interface SessionInfo {
    id: number;
    outlet?: string | null;
    opened_at?: string | null;
}

interface Props {
    session: SessionInfo;
    products: PosProduct[];
}

export default function Register({ session, products }: Props) {
    const { flash } = usePage<PageProps & {
        flash?: {
            pos_cart_error?: {
                message: string;
                unavailable_products?: { product_id: number }[];
            };
        };
    }>().props;
    const [query, setQuery] = useState('');
    const [cart, setCart] = useState<CartLine[]>([]);
    const [unavailableIds, setUnavailableIds] = useState<number[]>([]);
    const [errorMessage, setErrorMessage] = useState<string | null>(null);
    const [paymentOpen, setPaymentOpen] = useState(false);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        const cartError = flash?.pos_cart_error;
        if (!cartError) return;
        setErrorMessage(cartError.message);
        setUnavailableIds(
            (cartError.unavailable_products ?? []).map((p) => p.product_id),
        );
        setPaymentOpen(false);
    }, [flash?.pos_cart_error]);

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        if (!q) return products;
        return products.filter((p) => p.name.toLowerCase().includes(q));
    }, [products, query]);

    const total = cart.reduce((sum, line) => sum + line.unit_price * line.quantity, 0);

    const addProduct = (product: PosProduct) => {
        if (product.disabled) return;
        setUnavailableIds([]);
        setErrorMessage(null);
        setCart((prev) => {
            const existing = prev.find((l) => l.product_id === product.id);
            if (existing) {
                return prev.map((l) =>
                    l.product_id === product.id ? { ...l, quantity: l.quantity + 1 } : l,
                );
            }
            return [
                ...prev,
                {
                    product_id: product.id,
                    name: product.name,
                    unit_price: product.selling_price,
                    quantity: 1,
                },
            ];
        });
    };

    const updateQty = (productId: number, delta: number) => {
        setUnavailableIds([]);
        setErrorMessage(null);
        setCart((prev) =>
            prev
                .map((l) =>
                    l.product_id === productId
                        ? { ...l, quantity: Math.max(0, l.quantity + delta) }
                        : l,
                )
                .filter((l) => l.quantity > 0),
        );
    };

    const setQty = (productId: number, qty: number) => {
        setUnavailableIds([]);
        setErrorMessage(null);
        const value = Math.max(0, qty);
        setCart((prev) =>
            value === 0
                ? prev.filter((l) => l.product_id !== productId)
                : prev.map((l) => (l.product_id === productId ? { ...l, quantity: value } : l)),
        );
    };

    const checkout = (paymentMethod: string, amountPaid: number) => {
        setSubmitting(true);
        setErrorMessage(null);
        setUnavailableIds([]);

        router.post(
            '/pos/orders',
            {
                items: cart.map((l) => ({ product_id: l.product_id, quantity: l.quantity })),
                payment_method: paymentMethod,
                amount_paid: amountPaid,
            },
            {
                preserveScroll: true,
                onError: (errors) => {
                    setErrorMessage(
                        (errors.checkout as string) ?? 'Gagal memproses transaksi.',
                    );
                },
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <PosLayout
            title="Kasir"
            branch={session.outlet}
            actions={
                <Link
                    href="/pos/session/close"
                    className="rounded-md border border-border px-3 py-2 text-sm font-medium hover:bg-accent"
                >
                    Tutup Sesi
                </Link>
            }
        >
            <Head title="Kasir POS" />

            {errorMessage && (
                <div className="mb-4 rounded-lg border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
                    {errorMessage}
                </div>
            )}

            <div className="grid gap-4 lg:grid-cols-5">
                <section className="lg:col-span-3">
                    <div className="relative mb-3">
                        <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={query}
                            onChange={(e) => setQuery(e.target.value)}
                            placeholder="Cari produk..."
                            className="h-11 pl-9"
                        />
                    </div>

                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                        {filtered.map((product) => (
                            <button
                                key={product.id}
                                type="button"
                                disabled={product.disabled}
                                onClick={() => addProduct(product)}
                                className={cn(
                                    'flex min-h-[100px] flex-col items-start justify-between rounded-xl border border-border bg-card p-3 text-left transition hover:border-primary hover:shadow-sm',
                                    product.disabled && 'cursor-not-allowed opacity-50',
                                )}
                            >
                                <span className="text-sm font-semibold leading-snug">{product.name}</span>
                                <div className="mt-2 w-full">
                                    <p className="text-base font-bold">{formatRupiah(product.selling_price)}</p>
                                    {product.recipe_type === 'batch' && product.disabled && (
                                        <p className="text-xs text-destructive">Habis</p>
                                    )}
                                    {product.recipe_type === 'batch' && product.max_portions < 0 && (
                                        <p className="text-xs text-amber-600">Stok minus ({product.max_portions})</p>
                                    )}
                                    {product.recipe_type === 'batch' && !product.disabled && (
                                        <p className="text-xs text-muted-foreground">~{product.max_portions} pcs</p>
                                    )}
                                </div>
                            </button>
                        ))}
                    </div>
                </section>

                <section className="lg:col-span-2">
                    <div className="sticky top-20 rounded-xl border border-border bg-card p-4 shadow-sm">
                        <h2 className="text-lg font-semibold">Keranjang</h2>

                        {cart.length === 0 ? (
                            <p className="mt-4 text-sm text-muted-foreground">Belum ada item.</p>
                        ) : (
                            <ul className="mt-3 max-h-[50vh] space-y-3 overflow-y-auto">
                                {cart.map((line) => (
                                    <li
                                        key={line.product_id}
                                        className={cn(
                                            'rounded-lg border px-3 py-2',
                                            unavailableIds.includes(line.product_id)
                                                ? 'border-destructive bg-destructive/5'
                                                : 'border-border',
                                        )}
                                    >
                                        <div className="flex items-start justify-between gap-2">
                                            <div>
                                                <p className="font-medium">{line.name}</p>
                                                <p className="text-xs text-muted-foreground">
                                                    {formatRupiah(line.unit_price)} / item
                                                </p>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => updateQty(line.product_id, -line.quantity)}
                                                className="text-muted-foreground hover:text-destructive"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </div>
                                        <div className="mt-2 flex items-center justify-between">
                                            <div className="flex items-center gap-2">
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    className="h-8 w-8"
                                                    onClick={() => updateQty(line.product_id, -1)}
                                                >
                                                    <Minus className="h-4 w-4" />
                                                </Button>
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    className="h-8 w-14 px-1 text-center"
                                                    value={line.quantity}
                                                    onChange={(e) =>
                                                        setQty(line.product_id, Number(e.target.value))
                                                    }
                                                />
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="icon"
                                                    className="h-8 w-8"
                                                    onClick={() => updateQty(line.product_id, 1)}
                                                >
                                                    <Plus className="h-4 w-4" />
                                                </Button>
                                            </div>
                                            <span className="font-semibold">
                                                {formatRupiah(line.unit_price * line.quantity)}
                                            </span>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        )}

                        <div className="mt-4 flex items-center justify-between border-t border-border pt-4">
                            <span className="text-sm text-muted-foreground">Total</span>
                            <span className="text-xl font-bold">{formatRupiah(total)}</span>
                        </div>

                        <Button
                            type="button"
                            className="mt-4 h-12 w-full text-base"
                            disabled={cart.length === 0}
                            onClick={() => setPaymentOpen(true)}
                        >
                            Bayar
                        </Button>
                    </div>
                </section>
            </div>

            <PaymentModal
                open={paymentOpen}
                onClose={() => setPaymentOpen(false)}
                total={total}
                submitting={submitting}
                onConfirm={checkout}
            />
        </PosLayout>
    );
}

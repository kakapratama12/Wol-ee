import { FormEventHandler } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { ArrowLeft, Wrench } from 'lucide-react';
import PosLayout from '@/Layouts/PosLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

interface Outlet {
    id: number;
    name: string;
}

interface Ingredient {
    id: number;
    name: string;
    unit: string;
}

interface Reason {
    value: string;
    label: string;
}

interface Props {
    outlet: Outlet;
    ingredients: Ingredient[];
    reasons: Reason[];
}

export default function Adjust({ outlet, ingredients, reasons }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        ingredient_id: '',
        adjustment: '',
        unit: '',
        reason: '',
        note: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(`/pos/outlets/${outlet.id}/stock/adjust`, {
            onSuccess: () => {
                router.visit('/pos/stock');
            },
        });
    };

    const selectedIngredient = ingredients.find((i) => i.id === Number(data.ingredient_id));

    return (
        <PosLayout title="Adjust Stok" branch={outlet.name}>
            <Head title="Adjust Stok" />

            <div className="mx-auto max-w-md space-y-6">
                {/* Header */}
                <div className="flex items-center gap-3">
                    <Link href="/pos/stock">
                        <Button variant="ghost" size="icon" className="h-10 w-10">
                            <ArrowLeft className="h-5 w-5" />
                        </Button>
                    </Link>
                    <div>
                        <h2 className="text-lg font-semibold">Adjust Stok</h2>
                        <p className="text-sm text-muted-foreground">Catat susut, rusak, atau expired</p>
                    </div>
                </div>

                {/* Info */}
                <div className="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                    <p className="font-medium">⚠️ Perhatian</p>
                    <p className="mt-1">Stok yang dikurangi akan tercatat sebagai kerugian. Stok yang ditambah akan tercatat sebagai koreksi.</p>
                </div>

                {/* Form */}
                <form onSubmit={submit} className="rounded-xl border border-border bg-card p-6 shadow-sm space-y-4">
                    <div>
                        <Label htmlFor="ingredient_id">Bahan</Label>
                        <select
                            id="ingredient_id"
                            className="mt-1 flex h-10 w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                            value={data.ingredient_id}
                            onChange={(e) => {
                                setData('ingredient_id', e.target.value);
                                const ingredient = ingredients.find((i) => i.id === Number(e.target.value));
                                if (ingredient) {
                                    setData('unit', ingredient.unit);
                                }
                            }}
                        >
                            <option value="">Pilih bahan...</option>
                            {ingredients.map((ingredient) => (
                                <option key={ingredient.id} value={ingredient.id}>
                                    {ingredient.name}
                                </option>
                            ))}
                        </select>
                        {errors.ingredient_id && (
                            <p className="mt-1 text-sm text-destructive">{errors.ingredient_id}</p>
                        )}
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <Label htmlFor="adjustment">Jumlah</Label>
                            <Input
                                id="adjustment"
                                type="number"
                                step="any"
                                className="mt-1"
                                value={data.adjustment}
                                onChange={(e) => setData('adjustment', e.target.value)}
                                placeholder="Kurangi = minus (-)"
                            />
                            <p className="mt-1 text-xs text-muted-foreground">
                                Kurangi stok: -5 | Tambah stok: +5
                            </p>
                            {errors.adjustment && (
                                <p className="mt-1 text-sm text-destructive">{errors.adjustment}</p>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="unit">Satuan</Label>
                            <Input
                                id="unit"
                                className="mt-1"
                                value={data.unit}
                                onChange={(e) => setData('unit', e.target.value)}
                                placeholder="kg, liter, pcs"
                                readOnly={!!selectedIngredient}
                            />
                            {errors.unit && (
                                <p className="mt-1 text-sm text-destructive">{errors.unit}</p>
                            )}
                        </div>
                    </div>

                    <div>
                        <Label htmlFor="reason">Alasan</Label>
                        <select
                            id="reason"
                            className="mt-1 flex h-10 w-full rounded-md border border-border bg-background px-3 py-2 text-sm"
                            value={data.reason}
                            onChange={(e) => setData('reason', e.target.value)}
                        >
                            <option value="">Pilih alasan...</option>
                            {reasons.map((reason) => (
                                <option key={reason.value} value={reason.value}>
                                    {reason.label}
                                </option>
                            ))}
                        </select>
                        {errors.reason && (
                            <p className="mt-1 text-sm text-destructive">{errors.reason}</p>
                        )}
                    </div>

                    <div>
                        <Label htmlFor="note">Catatan (opsional)</Label>
                        <Input
                            id="note"
                            className="mt-1"
                            value={data.note}
                            onChange={(e) => setData('note', e.target.value)}
                            placeholder="Detail tambahan..."
                        />
                    </div>

                    <Button type="submit" disabled={processing} className="h-12 w-full text-base">
                        <Wrench className="mr-2 h-5 w-5" />
                        {processing ? 'Menyimpan...' : 'Simpan Adjustmen'}
                    </Button>
                </form>
            </div>
        </PosLayout>
    );
}

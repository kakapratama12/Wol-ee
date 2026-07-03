import { FormEventHandler } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import { ArrowLeft, ShoppingCart } from 'lucide-react';
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

interface Props {
    outlet: Outlet | null;
    ingredients: Ingredient[];
}

export default function Purchase({ outlet, ingredients }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        ingredient_id: '',
        quantity: '',
        unit: '',
        note: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/pos/stock/purchase', {
            onSuccess: () => {
                router.visit('/pos/stock');
            },
        });
    };

    const selectedIngredient = ingredients.find((i) => i.id === Number(data.ingredient_id));

    return (
        <PosLayout title="Beli Bahan" branch={outlet?.name ?? 'Beli Bahan'}>
            <Head title="Beli Bahan" />

            <div className="mx-auto max-w-md space-y-6">
                {/* Header */}
                <div className="flex items-center gap-3">
                    <Link href="/pos/stock">
                        <Button variant="ghost" size="icon" className="h-10 w-10">
                            <ArrowLeft className="h-5 w-5" />
                        </Button>
                    </Link>
                    <div>
                        <h2 className="text-lg font-semibold">Beli Bahan</h2>
                        <p className="text-sm text-muted-foreground">Pembelian direct dari outlet</p>
                    </div>
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
                            <Label htmlFor="quantity">Jumlah</Label>
                            <Input
                                id="quantity"
                                type="number"
                                min={0.01}
                                step="any"
                                className="mt-1"
                                value={data.quantity}
                                onChange={(e) => setData('quantity', e.target.value)}
                                placeholder="0"
                            />
                            {errors.quantity && (
                                <p className="mt-1 text-sm text-destructive">{errors.quantity}</p>
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
                        <Label htmlFor="note">Catatan (opsional)</Label>
                        <Input
                            id="note"
                            className="mt-1"
                            value={data.note}
                            onChange={(e) => setData('note', e.target.value)}
                            placeholder="Contoh: Beli dadakan, supplier X"
                        />
                    </div>

                    <Button type="submit" disabled={processing} className="h-12 w-full text-base">
                        <ShoppingCart className="mr-2 h-5 w-5" />
                        {processing ? 'Menyimpan...' : 'Catat Pembelian'}
                    </Button>
                </form>
            </div>
        </PosLayout>
    );
}

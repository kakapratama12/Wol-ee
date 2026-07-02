import { FormEventHandler } from 'react';
import { Head, useForm } from '@inertiajs/react';
import PosLayout from '@/Layouts/PosLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

interface Props {
    outlet?: string | null;
}

export default function OpenSession({ outlet }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        opening_cash: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/pos/session/open');
    };

    return (
        <PosLayout title="Buka Sesi" branch={outlet}>
            <Head title="Buka Sesi Kasir" />

            <div className="mx-auto max-w-md rounded-xl border border-border bg-card p-6 shadow-sm">
                <h2 className="text-xl font-semibold">Mulai shift kasir</h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Uang tunai fisik di laci saat buka toko.
                </p>

                <form onSubmit={submit} className="mt-6 space-y-4">
                    <div>
                        <Label htmlFor="opening_cash">Modal awal (Rp)</Label>
                        <Input
                            id="opening_cash"
                            type="number"
                            min={0}
                            inputMode="numeric"
                            className="mt-1 h-12 text-lg"
                            value={data.opening_cash}
                            onChange={(e) => setData('opening_cash', e.target.value)}
                            placeholder="0"
                            autoFocus
                        />
                        {errors.opening_cash && (
                            <p className="mt-1 text-sm text-destructive">{errors.opening_cash}</p>
                        )}
                    </div>

                    <Button type="submit" disabled={processing} className="h-12 w-full text-base">
                        Mulai Sesi
                    </Button>
                </form>
            </div>
        </PosLayout>
    );
}

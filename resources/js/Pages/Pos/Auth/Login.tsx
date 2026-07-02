import { FormEventHandler } from 'react';
import { Head, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import InputError from '@/Components/InputError';

export default function PosLogin({ status }: { status?: string }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post('/pos/login', { onFinish: () => reset('password') });
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-background p-4">
            <Head title="Login Kasir POS" />
            <div className="w-full max-w-md rounded-xl border border-border bg-card p-6 shadow-sm">
                <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                    Wol-ee POS
                </p>
                <h1 className="mt-1 text-2xl font-semibold">Login Kasir</h1>

                {status && <p className="mt-4 text-sm text-green-600">{status}</p>}

                <form onSubmit={submit} className="mt-6 space-y-4">
                    <div>
                        <Label htmlFor="email">Email</Label>
                        <Input
                            id="email"
                            type="email"
                            className="mt-1"
                            value={data.email}
                            onChange={(e) => setData('email', e.target.value)}
                            autoFocus
                        />
                        <InputError message={errors.email} className="mt-1" />
                    </div>
                    <div>
                        <Label htmlFor="password">Password</Label>
                        <Input
                            id="password"
                            type="password"
                            className="mt-1"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                        />
                        <InputError message={errors.password} className="mt-1" />
                    </div>
                    <Button type="submit" disabled={processing} className="h-12 w-full">
                        Masuk POS
                    </Button>
                </form>
            </div>
        </div>
    );
}

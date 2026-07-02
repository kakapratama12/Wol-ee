import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';

export default function Edit() {
    return (
        <AppLayout title="Ganti Password">
            <Head title="Ganti Password" />

            <div className="mx-auto max-w-xl">
                <Link
                    href="/dashboard"
                    className="mb-4 inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Kembali
                </Link>

                <div className="rounded-lg border border-border bg-card p-6 shadow-sm">
                    <UpdatePasswordForm />
                </div>
            </div>
        </AppLayout>
    );
}

import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';

export default function Edit() {
    return (
        <AppLayout title="Ganti Password">
            <Head title="Ganti Password" />

            <div className="mx-auto max-w-xl">
                <div className="rounded-lg border border-border bg-card p-6 shadow-sm">
                    <UpdatePasswordForm />
                </div>
            </div>
        </AppLayout>
    );
}

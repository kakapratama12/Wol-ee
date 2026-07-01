import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, MessageCircle } from 'lucide-react';

export default function ForgotPassword() {
    const whatsappUrl =
        'https://wa.me/6281225056948?text=' +
        encodeURIComponent(
            'Halo, saya mau reset password untuk akun Wol-ee saya.',
        );

    return (
        <GuestLayout>
            <Head title="Lupa Password" />

            <div className="text-center">
                <Link
                    href={route('login')}
                    className="mb-4 inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700"
                >
                    <ArrowLeft className="h-4 w-4" />
                    Kembali
                </Link>

                <div className="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                    <MessageCircle className="h-6 w-6 text-gray-600" />
                </div>

                <h2 className="text-lg font-semibold text-gray-900">
                    Lupa Password?
                </h2>

                <p className="mt-2 text-sm text-gray-600">
                    Hubungi tim support kami untuk bantuan reset password.
                </p>

                <Link
                    href={whatsappUrl}
                    target="_blank"
                    rel="noopener noreferrer"
                    className="mt-6 inline-flex items-center gap-2 rounded-lg bg-[#25D366] px-5 py-2.5 text-sm font-medium text-white shadow-sm transition hover:bg-[#1da851]"
                >
                    <MessageCircle className="h-4 w-4" />
                    Diskusikan via WhatsApp
                </Link>

                <p className="mt-6 text-xs text-gray-500">
                    Klik tombol di atas untuk chat langsung dengan tim support.
                </p>
            </div>
        </GuestLayout>
    );
}

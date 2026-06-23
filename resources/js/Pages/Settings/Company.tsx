import { Head, useForm } from '@inertiajs/react';
import { Building2, Save } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import InputError from '@/Components/InputError';

interface TenantData {
    name: string;
    address: string | null;
    phone: string | null;
    email: string | null;
    bank_name: string | null;
    bank_account: string | null;
    bank_account_name: string | null;
}

interface Props {
    tenant: TenantData;
}

export default function Company({ tenant }: Props) {
    const { data, setData, put, processing, errors } = useForm({
        name: tenant.name ?? '',
        address: tenant.address ?? '',
        phone: tenant.phone ?? '',
        email: tenant.email ?? '',
        bank_name: tenant.bank_name ?? '',
        bank_account: tenant.bank_account ?? '',
        bank_account_name: tenant.bank_account_name ?? '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put('/settings/company');
    };

    return (
        <AppLayout title="Pengaturan Perusahaan">
            <Head title="Pengaturan Perusahaan" />

            <div className="mx-auto max-w-2xl space-y-6">
                <form onSubmit={submit} className="space-y-6">
                    {/* Company Info */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Building2 className="h-5 w-5" />
                                Informasi Perusahaan
                            </CardTitle>
                            <CardDescription>
                                Informasi dasar perusahaan yang akan ditampilkan pada invoice dan dokumen lainnya.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Nama Perusahaan *</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    placeholder="Masukkan nama perusahaan"
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="address">Alamat</Label>
                                <Input
                                    id="address"
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                    placeholder="Alamat perusahaan"
                                />
                                <InputError message={errors.address} />
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="phone">Telepon</Label>
                                    <Input
                                        id="phone"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                        placeholder="Nomor telepon"
                                    />
                                    <InputError message={errors.phone} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                        placeholder="email@perusahaan.com"
                                    />
                                    <InputError message={errors.email} />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Bank Info */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Informasi Bank</CardTitle>
                            <CardDescription>
                                Informasi rekening bank untuk pembayaran invoice.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="bank_name">Nama Bank</Label>
                                <Input
                                    id="bank_name"
                                    value={data.bank_name}
                                    onChange={(e) => setData('bank_name', e.target.value)}
                                    placeholder="Contoh: Bank Central Asia"
                                />
                                <InputError message={errors.bank_name} />
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="space-y-2">
                                    <Label htmlFor="bank_account">No. Rekening</Label>
                                    <Input
                                        id="bank_account"
                                        value={data.bank_account}
                                        onChange={(e) => setData('bank_account', e.target.value)}
                                        placeholder="Nomor rekening"
                                    />
                                    <InputError message={errors.bank_account} />
                                </div>

                                <div className="space-y-2">
                                    <Label htmlFor="bank_account_name">Atas Nama Rekening</Label>
                                    <Input
                                        id="bank_account_name"
                                        value={data.bank_account_name}
                                        onChange={(e) => setData('bank_account_name', e.target.value)}
                                        placeholder="Nama pemilik rekening"
                                    />
                                    <InputError message={errors.bank_account_name} />
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Submit */}
                    <div className="flex justify-end">
                        <Button type="submit" disabled={processing}>
                            <Save className="mr-2 h-4 w-4" />
                            {processing ? 'Menyimpan...' : 'Simpan Pengaturan'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}

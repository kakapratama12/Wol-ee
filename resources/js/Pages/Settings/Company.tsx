import { Head, useForm } from '@inertiajs/react';
import { Building2, Save, Upload, X } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import InputError from '@/Components/InputError';
import { useState, useRef } from 'react';

interface TenantData {
    name: string;
    address: string | null;
    phone: string | null;
    email: string | null;
    bank_name: string | null;
    bank_account: string | null;
    bank_account_name: string | null;
    logo: string | null;
    logo_url: string | null;
}

interface Props {
    tenant: TenantData;
}

export default function Company({ tenant }: Props) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);

    const { data, setData, put, processing, errors } = useForm({
        name: tenant.name ?? '',
        address: tenant.address ?? '',
        phone: tenant.phone ?? '',
        email: tenant.email ?? '',
        bank_name: tenant.bank_name ?? '',
        bank_account: tenant.bank_account ?? '',
        bank_account_name: tenant.bank_account_name ?? '',
        logo: null as File | null,
        remove_logo: '',
    });

    const handleLogoChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (file) {
            setData('logo', file);
            setData('remove_logo', '');
            const reader = new FileReader();
            reader.onload = (ev) => {
                setPreviewUrl(ev.target?.result as string);
            };
            reader.readAsDataURL(file);
        }
    };

    const handleRemoveLogo = () => {
        setData('logo', null);
        setData('remove_logo', '1');
        setPreviewUrl(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        put('/settings/company');
    };

    const showCurrentLogo = tenant.logo_url && !previewUrl && data.remove_logo !== '1';
    const showPreview = previewUrl && data.remove_logo !== '1';

    return (
        <AppLayout title="Pengaturan Perusahaan">
            <Head title="Pengaturan Perusahaan" />

            <div className="mx-auto max-w-2xl space-y-6">
                <form onSubmit={submit} className="space-y-6">
                    {/* Logo Section */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Upload className="h-5 w-5" />
                                Logo Perusahaan
                            </CardTitle>
                            <CardDescription>
                                Logo perusahaan yang ditampilkan pada invoice. Maks 2MB (JPG, PNG,
                                SVG).
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {/* Current Logo */}
                            {showCurrentLogo && tenant.logo_url && (
                                <div className="space-y-2">
                                    <Label>Logo Saat Ini</Label>
                                    <div className="flex items-center gap-4">
                                        <div className="h-20 w-20 rounded-lg border bg-muted flex items-center justify-center overflow-hidden">
                                            <img
                                                src={tenant.logo_url}
                                                alt="Logo perusahaan"
                                                className="h-full w-full object-contain"
                                            />
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {tenant.logo}
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* Preview of new logo */}
                            {showPreview && previewUrl && (
                                <div className="space-y-2">
                                    <Label>Logo Baru</Label>
                                    <div className="flex items-center gap-4">
                                        <div className="h-20 w-20 rounded-lg border bg-muted flex items-center justify-center overflow-hidden">
                                            <img
                                                src={previewUrl}
                                                alt="Preview logo"
                                                className="h-full w-full object-contain"
                                            />
                                        </div>
                                        <div className="text-sm text-muted-foreground">
                                            {data.logo?.name}
                                        </div>
                                    </div>
                                </div>
                            )}

                            {/* File Input */}
                            <div className="space-y-2">
                                <Label htmlFor="logo">Upload Logo Baru</Label>
                                <Input
                                    ref={fileInputRef}
                                    id="logo"
                                    type="file"
                                    accept=".jpg,.jpeg,.png,.svg"
                                    onChange={handleLogoChange}
                                    className="file:mr-2 file:border-0 file:bg-transparent file:text-sm file:font-medium"
                                />
                                <InputError message={errors.logo} />
                            </div>

                            {/* Remove Logo */}
                            {(tenant.logo_url || showPreview) && (
                                <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={handleRemoveLogo}
                                        className="inline-flex items-center gap-1 text-sm text-destructive hover:text-destructive/80"
                                    >
                                        <X className="h-4 w-4" />
                                        {showPreview ? 'Batal Upload' : 'Hapus Logo'}
                                    </button>
                                    {data.remove_logo === '1' && (
                                        <span className="text-sm text-muted-foreground">
                                            (Logo akan dihapus saat disimpan)
                                        </span>
                                    )}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    {/* Company Info */}
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Building2 className="h-5 w-5" />
                                Informasi Perusahaan
                            </CardTitle>
                            <CardDescription>
                                Informasi dasar perusahaan yang akan ditampilkan pada invoice dan
                                dokumen lainnya.
                            </CardDescription>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label htmlFor="name">Nama Usaha</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    disabled
                                    className="bg-muted"
                                />
                                <p className="text-xs text-muted-foreground">
                                    Hubungi tim support apabila ingin mengganti nama usaha.
                                </p>
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
                                        onChange={(e) =>
                                            setData('bank_account_name', e.target.value)
                                        }
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

import { Head, useForm } from '@inertiajs/react';
import { Info } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Select } from '@/Components/ui/select';
import { Badge } from '@/Components/ui/badge';
import { formatRupiah } from '@/lib/format';

interface Defaults {
    business_type: string;
    omset: number | string;
    cogs: number | string;
    expense: number | string;
    waste_percent: number | string;
}

interface Result {
    omset: number;
    cogs: number;
    cogs_with_waste: number;
    expense: number;
    taxable_profit: number;
    pp23: number;
    normal: number;
    difference: number;
    recommended: 'pp23' | 'normal';
    saving: number;
}

interface Props {
    defaults: Defaults;
    result: Result | null;
}

export default function TaxSimulator({ defaults, result }: Props) {
    const form = useForm({
        business_type: defaults.business_type ?? 'perorangan',
        omset: String(defaults.omset ?? ''),
        cogs: String(defaults.cogs ?? ''),
        expense: String(defaults.expense ?? ''),
        waste_percent: String(defaults.waste_percent ?? '10'),
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/tax', { preserveScroll: true });
    };

    return (
        <AppLayout title="Tax Simulator">
            <Head title="Tax Simulator" />

            <div className="grid gap-6 *:min-w-0 lg:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Input Simulasi</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div>
                                <Label htmlFor="business_type">Tipe bisnis</Label>
                                <Select
                                    id="business_type"
                                    value={form.data.business_type}
                                    onChange={(e) => form.setData('business_type', e.target.value)}
                                >
                                    <option value="perorangan">
                                        Perorangan (PPh 21 progresif)
                                    </option>
                                    <option value="cv">CV (Badan 22%)</option>
                                    <option value="pt">PT (Badan 22%)</option>
                                </Select>
                            </div>
                            <div>
                                <Label htmlFor="omset">Omset (Rp)</Label>
                                <Input
                                    id="omset"
                                    type="number"
                                    step="1"
                                    value={form.data.omset}
                                    onChange={(e) => form.setData('omset', e.target.value)}
                                />
                                {form.errors.omset && (
                                    <p className="mt-1 text-xs text-destructive">
                                        {form.errors.omset}
                                    </p>
                                )}
                            </div>
                            <div>
                                <Label htmlFor="cogs">COGS (Rp)</Label>
                                <Input
                                    id="cogs"
                                    type="number"
                                    step="1"
                                    value={form.data.cogs}
                                    onChange={(e) => form.setData('cogs', e.target.value)}
                                />
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Otomatis dari tracking, bisa diubah manual.
                                </p>
                            </div>
                            <div className="grid grid-cols-2 gap-3 *:min-w-0">
                                <div>
                                    <Label htmlFor="expense">Biaya lain (Rp)</Label>
                                    <Input
                                        id="expense"
                                        type="number"
                                        step="1"
                                        value={form.data.expense}
                                        onChange={(e) => form.setData('expense', e.target.value)}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="waste_percent">Waste (%)</Label>
                                    <Input
                                        id="waste_percent"
                                        type="number"
                                        step="0.1"
                                        value={form.data.waste_percent}
                                        onChange={(e) =>
                                            form.setData('waste_percent', e.target.value)
                                        }
                                    />
                                </div>
                            </div>
                            <Button type="submit" className="w-full" disabled={form.processing}>
                                Hitung Estimasi Pajak
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <div className="space-y-4">
                    {result ? (
                        <>
                            <div className="grid grid-cols-2 gap-4 *:min-w-0">
                                <Card
                                    className={
                                        result.recommended === 'pp23'
                                            ? 'border-primary ring-1 ring-primary'
                                            : ''
                                    }
                                >
                                    <CardContent className="p-5">
                                        <div className="flex items-center justify-between">
                                            <p className="text-sm text-muted-foreground">
                                                PP 23 (Final 0.5%)
                                            </p>
                                            {result.recommended === 'pp23' && (
                                                <Badge>Lebih hemat</Badge>
                                            )}
                                        </div>
                                        <p className="mt-2 text-2xl font-bold">
                                            {formatRupiah(result.pp23)}
                                        </p>
                                    </CardContent>
                                </Card>
                                <Card
                                    className={
                                        result.recommended === 'normal'
                                            ? 'border-primary ring-1 ring-primary'
                                            : ''
                                    }
                                >
                                    <CardContent className="p-5">
                                        <div className="flex items-center justify-between">
                                            <p className="text-sm text-muted-foreground">
                                                Skema Normal
                                            </p>
                                            {result.recommended === 'normal' && (
                                                <Badge>Lebih hemat</Badge>
                                            )}
                                        </div>
                                        <p className="mt-2 text-2xl font-bold">
                                            {formatRupiah(result.normal)}
                                        </p>
                                    </CardContent>
                                </Card>
                            </div>

                            <Card>
                                <CardContent className="space-y-2 p-5 text-sm">
                                    <Row
                                        label="COGS + waste"
                                        value={formatRupiah(result.cogs_with_waste)}
                                    />
                                    <Row
                                        label="Profit taxable"
                                        value={formatRupiah(result.taxable_profit)}
                                    />
                                    <div className="my-2 border-t border-border" />
                                    <Row
                                        label="Selisih kedua skema"
                                        value={formatRupiah(Math.abs(result.difference))}
                                        bold
                                    />
                                </CardContent>
                            </Card>

                            <div className="flex gap-2 rounded-lg bg-muted/50 p-3 text-xs text-muted-foreground">
                                <Info className="h-4 w-4 shrink-0" />
                                <p>
                                    Tool ini untuk estimasi perencanaan keuangan, bukan pengganti
                                    konsultan pajak. Konsultasikan laporan akhir dengan konsultan
                                    pajak atau akuntan bersertifikat.
                                </p>
                            </div>
                        </>
                    ) : (
                        <Card>
                            <CardContent className="flex h-full min-h-48 items-center justify-center p-8 text-center text-sm text-muted-foreground">
                                Isi form lalu klik "Hitung Estimasi Pajak" untuk melihat
                                perbandingan PP 23 vs Normal.
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AppLayout>
    );
}

function Row({ label, value, bold }: { label: string; value: string; bold?: boolean }) {
    return (
        <div className="flex items-center justify-between">
            <span className="text-muted-foreground">{label}</span>
            <span className={bold ? 'font-semibold' : ''}>{value}</span>
        </div>
    );
}

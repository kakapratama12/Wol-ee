import { Head, router } from '@inertiajs/react';
import { Bot, Copy, RefreshCw } from 'lucide-react';
import AppLayout from '@/Layouts/AppLayout';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';

interface Props {
    hasToken: boolean;
    tenantName: string;
    plainToken: string | null;
}

export default function BotIntegration({ hasToken, tenantName, plainToken }: Props) {
    const generate = () => {
        if (hasToken && !confirm('Token lama akan diganti. Bot harus dikonfigurasi ulang. Lanjutkan?')) {
            return;
        }
        router.post('/settings/bot/token');
    };

    const copyToken = async () => {
        if (!plainToken) return;
        await navigator.clipboard.writeText(plainToken);
    };

    return (
        <AppLayout title="Bot Integration">
            <Head title="Bot Integration" />

            <div className="mx-auto max-w-2xl space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <Bot className="h-5 w-5" />
                            Integrasi Telegram Bot
                        </CardTitle>
                        <CardDescription>
                            Token untuk tenant <strong>{tenantName}</strong>. Staff paste token ini saat
                            registrasi di bot Telegram.
                        </CardDescription>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        <div className="rounded-md border bg-muted/40 p-4 text-sm text-muted-foreground">
                            <p className="font-medium text-foreground">Cara pakai</p>
                            <ol className="mt-2 list-decimal space-y-1 pl-4">
                                <li>Generate token di bawah</li>
                                <li>Salin token (format: tenant_id:secret)</li>
                                <li>Staff buka bot → /start → paste token</li>
                                <li>Atau set di server bot: WOL_EE_API_TOKEN</li>
                            </ol>
                        </div>

                        {plainToken && (
                            <div className="space-y-2">
                                <Label>Token baru (salin sekarang)</Label>
                                <div className="flex gap-2">
                                    <Input readOnly value={plainToken} className="font-mono text-sm" />
                                    <Button type="button" variant="outline" onClick={copyToken}>
                                        <Copy className="h-4 w-4" />
                                    </Button>
                                </div>
                                <p className="text-xs text-amber-600">
                                    Token hanya ditampilkan sekali setelah generate.
                                </p>
                            </div>
                        )}

                        <div className="flex items-center justify-between rounded-md border p-4">
                            <div>
                                <p className="text-sm font-medium">Status token</p>
                                <p className="text-sm text-muted-foreground">
                                    {hasToken ? 'Token aktif' : 'Belum ada token'}
                                </p>
                            </div>
                            <Button onClick={generate}>
                                <RefreshCw className="mr-2 h-4 w-4" />
                                {hasToken ? 'Regenerate Token' : 'Generate Token'}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

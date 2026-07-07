import { Head, Link, router } from '@inertiajs/react';
import { useRef, useState } from 'react';
import { Bot, Check, Copy, History, RefreshCw } from 'lucide-react';
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
    const inputRef = useRef<HTMLInputElement>(null);
    const [copied, setCopied] = useState(false);
    const generate = () => {
        if (
            hasToken &&
            !confirm('Token lama akan diganti. Bot harus dikonfigurasi ulang. Lanjutkan?')
        ) {
            return;
        }
        router.post('/settings/bot/token');
    };

    const copyToken = async () => {
        if (!plainToken) return;
        try {
            await navigator.clipboard.writeText(plainToken);
            setCopied(true);
            setTimeout(() => setCopied(false), 3000);
        } catch {
            const input = inputRef.current;
            if (input) {
                input.select();
                input.setSelectionRange(0, plainToken.length);
                document.execCommand('copy');
                setCopied(true);
                setTimeout(() => setCopied(false), 3000);
            }
        }
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
                            Token untuk tenant <strong>{tenantName}</strong>. Staff paste token ini
                            saat registrasi di bot Telegram.
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
                                    <Input
                                        ref={inputRef}
                                        readOnly
                                        value={plainToken}
                                        className="font-mono text-sm"
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={copyToken}
                                        aria-label={copied ? 'Disalin' : 'Salin token'}
                                    >
                                        {copied ? (
                                            <Check className="h-4 w-4 text-green-600" />
                                        ) : (
                                            <Copy className="h-4 w-4" />
                                        )}
                                    </Button>
                                </div>
                                <p className="text-xs text-amber-600">
                                    Token hanya ditampilkan sekali setelah generate.
                                </p>
                                {copied && <p className="text-xs text-green-600">Disalin!</p>}
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

                        <Link href="/settings/bot/history">
                            <Button variant="outline" className="w-full">
                                <History className="mr-2 h-4 w-4" />
                                Lihat Riwayat Input Bot
                            </Button>
                        </Link>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

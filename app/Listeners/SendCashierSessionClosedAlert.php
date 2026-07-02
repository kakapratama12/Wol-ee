<?php

namespace App\Listeners;

use App\Events\CashierSessionClosed;
use App\Support\TelegramNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendCashierSessionClosedAlert implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(private readonly TelegramNotifier $notifier) {}

    public function handle(CashierSessionClosed $event): void
    {
        $session = $event->session->loadMissing(['outlet', 'user']);
        $summary = $event->summary;

        $this->notifier->send($this->buildMessage($session, $summary));
    }

    /**
     * @param  array<string, mixed>  $summary
     */
    private function buildMessage(\App\Models\CashierSession $session, array $summary): string
    {
        $branch = $session->outlet?->name ?? 'Outlet';
        $kasir = $session->user?->name ?? 'Kasir';
        $variance = (float) ($summary['variance'] ?? 0);
        $varianceLabel = $variance === 0.0
            ? 'pas'
            : ($variance > 0 ? sprintf('+%s', number_format($variance, 0, ',', '.')) : number_format($variance, 0, ',', '.'));

        $lines = [
            '<b>Sesi kasir ditutup</b>',
            sprintf('Cabang: %s', htmlspecialchars($branch, ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            sprintf('Kasir: %s', htmlspecialchars($kasir, ENT_QUOTES | ENT_HTML5, 'UTF-8')),
            sprintf('Omset: Rp %s', number_format((float) ($summary['total_omset'] ?? 0), 0, ',', '.')),
            sprintf('Tunai: Rp %s | QRIS: Rp %s | Transfer: Rp %s',
                number_format((float) ($summary['total_cash'] ?? 0), 0, ',', '.'),
                number_format((float) ($summary['total_qris'] ?? 0), 0, ',', '.'),
                number_format((float) ($summary['total_transfer'] ?? 0), 0, ',', '.'),
            ),
            sprintf('Kas diharapkan: Rp %s', number_format((float) ($summary['expected_cash'] ?? 0), 0, ',', '.')),
            sprintf('Kas aktual: Rp %s', number_format((float) ($summary['actual_cash'] ?? 0), 0, ',', '.')),
            sprintf('Selisih: %s', $varianceLabel),
        ];

        $salesSummary = $summary['sales_summary'] ?? [];
        if ($salesSummary !== []) {
            $lines[] = '';
            $lines[] = '<b>Penjualan:</b>';
            foreach ($salesSummary as $row) {
                $lines[] = sprintf(
                    '- %s: %d (Rp %s)',
                    htmlspecialchars((string) $row['product'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    (int) $row['quantity'],
                    number_format((float) $row['revenue'], 0, ',', '.'),
                );
            }
        }

        return implode("\n", $lines);
    }
}

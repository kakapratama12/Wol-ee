<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pengirim notifikasi ke Telegram. Bila kredensial belum dikonfigurasi
 * (mis. di dev/test), berperilaku no-op dan hanya menulis log — sehingga
 * fitur tidak pernah menggagalkan job karena config kosong.
 */
class TelegramNotifier
{
    public function __construct(
        private readonly ?string $botToken = null,
        private readonly ?string $chatId = null,
    ) {}

    public function isConfigured(): bool
    {
        return ! empty($this->botToken) && ! empty($this->chatId);
    }

    /**
     * Kirim pesan teks. Mengembalikan true bila terkirim, false bila no-op.
     * Melempar exception pada kegagalan HTTP agar job di-retry oleh queue.
     */
    public function send(string $message): bool
    {
        if (! $this->isConfigured()) {
            Log::info('[TelegramNotifier] dilewati (kredensial belum dikonfigurasi): '.$message);

            return false;
        }

        Http::asJson()
            ->post("https://api.telegram.org/bot{$this->botToken}/sendMessage", [
                'chat_id' => $this->chatId,
                'text' => $message,
                'parse_mode' => 'HTML',
            ])
            ->throw();

        return true;
    }
}

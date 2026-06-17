<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\BotTokenService;
use Illuminate\Console\Command;

class GenerateBotToken extends Command
{
    protected $signature = 'wol-ee:generate-bot-token {--tenant=}';

    protected $aliases = ['wolee:bot-token'];

    protected $description = 'Generate token bot per tenant (format: {tenant_id}:{secret})';

    public function handle(BotTokenService $botTokens): int
    {
        $tenant = $this->option('tenant')
            ? Tenant::query()->findOrFail($this->option('tenant'))
            : Tenant::query()->firstOrFail();

        $plainToken = $botTokens->generate($tenant);

        $this->info('Token bot berhasil dibuat untuk tenant: '.$tenant->name);
        $this->newLine();
        $this->line('Simpan token ini di konfigurasi bot (header: Authorization: Bearer <token>):');
        $this->newLine();
        $this->line($plainToken);

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class GenerateBotToken extends Command
{
    protected $signature = 'wolee:bot-token {email=bot@wol-ee.local} {--name=Telegram Bot}';

    protected $description = 'Buat/ambil user bot dan terbitkan Sanctum token untuk integrasi bot Telegram';

    public function handle(): int
    {
        $email = $this->argument('email');

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $this->option('name'),
                'password' => Hash::make(Str::random(32)),
                'role' => User::ROLE_ADMIN,
            ],
        );

        $token = $user->createToken('telegram-bot')->plainTextToken;

        $this->info('Token bot berhasil dibuat untuk '.$user->email);
        $this->newLine();
        $this->line('Simpan token ini di konfigurasi bot (header: Authorization: Bearer <token>):');
        $this->newLine();
        $this->line($token);

        return self::SUCCESS;
    }
}

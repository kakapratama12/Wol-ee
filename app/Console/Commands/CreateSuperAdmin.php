<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Facades\Validator;

class CreateSuperAdmin extends Command
{
    protected $signature = 'wol-ee:create-super-admin
        {--email= : Email super admin}
        {--password= : Password super admin}
        {--name=Super Admin : Nama super admin}';

    protected $description = 'Create or update the first Wol-ee super admin account';

    public function handle(): int
    {
        $email = (string) ($this->option('email') ?: $this->ask('Email'));
        $password = (string) ($this->option('password') ?: $this->secret('Password'));
        $name = (string) ($this->option('name') ?: 'Super Admin');

        $validator = Validator::make(
            compact('email', 'password', 'name'),
            [
                'email' => ['required', 'email'],
                'password' => ['required', Password::min(10)],
                'name' => ['required', 'string', 'max:255'],
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        User::updateOrCreate(
            ['email' => $email, 'tenant_id' => null],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => User::ROLE_SUPER_ADMIN,
                'email_verified_at' => now(),
            ],
        );

        $this->info('Super admin siap: '.$email);

        return self::SUCCESS;
    }
}

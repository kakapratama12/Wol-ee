<?php

namespace Database\Seeders;

use App\Models\Invoice;
use App\Models\Partner;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class PartnerSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::query()->where('slug', 'kafe-contoh')->firstOrFail();

        $supplier = Partner::withoutGlobalScope('tenant')->updateOrCreate(
            ['name' => 'CV Tepung Sejahtera', 'tenant_id' => $tenant->id],
            [
                'type' => Partner::TYPE_SUPPLIER,
                'contact' => 'Pak Budi',
                'phone' => '081234567890',
            ],
        );

        $customer = Partner::withoutGlobalScope('tenant')->updateOrCreate(
            ['name' => 'Kantor Pak Joko', 'tenant_id' => $tenant->id],
            [
                'type' => Partner::TYPE_CUSTOMER,
                'contact' => 'Pak Joko',
                'phone' => '081234567891',
            ],
        );

        $owner = User::query()
            ->where('email', 'owner@wol-ee.local')
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($owner) {
            auth()->login($owner);
        }

        Invoice::withoutGlobalScope('tenant')->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'invoice_number' => 'INV-'.Carbon::now()->format('Ym').'-001',
            ],
            [
                'partner_id' => $customer->id,
                'amount' => 5000000,
                'paid_amount' => 0,
                'due_date' => Carbon::now()->addDays(14)->toDateString(),
                'status' => Invoice::STATUS_OUTSTANDING,
                'note' => 'Tagihan catering bulan ini',
            ],
        );

        Invoice::withoutGlobalScope('tenant')->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'invoice_number' => 'INV-'.Carbon::now()->format('Ym').'-002',
            ],
            [
                'partner_id' => $customer->id,
                'amount' => 3000000,
                'paid_amount' => 1000000,
                'due_date' => Carbon::now()->subDays(45)->toDateString(),
                'status' => Invoice::STATUS_PARTIAL,
                'note' => 'Tagihan catering bulan lalu',
            ],
        );

        $this->command?->info("Partner sample seeded for tenant: {$tenant->name}");
        unset($supplier, $customer);
    }
}

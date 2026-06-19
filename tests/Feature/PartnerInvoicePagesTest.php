<?php

use App\Models\Invoice;
use App\Models\Partner;
use App\Models\Tenant;
use App\Models\User;

it('owner dan admin bisa akses halaman partner dan invoice', function () {
    $tenant = Tenant::factory()->create();

    $owner = User::factory()->create([
        'role' => 'owner',
        'tenant_id' => $tenant->id,
        'email_verified_at' => now(),
    ]);

    $admin = User::factory()->create([
        'role' => 'admin',
        'tenant_id' => $tenant->id,
        'email_verified_at' => now(),
    ]);

    $partner = Partner::create([
        'tenant_id' => $tenant->id,
        'name' => 'Kantor Pak Joko',
        'type' => 'customer',
    ]);

    $invoice = Invoice::create([
        'tenant_id' => $tenant->id,
        'partner_id' => $partner->id,
        'invoice_number' => 'INV-'.now()->format('Ym').'-099',
        'amount' => 1000000,
        'paid_amount' => 0,
        'due_date' => now()->addDays(7),
        'status' => 'outstanding',
    ]);

    $this->actingAs($owner)->get('/partners')->assertOk();
    $this->actingAs($owner)->get("/partners/{$partner->id}")->assertOk();
    $this->actingAs($owner)->get('/invoices')->assertOk();
    $this->actingAs($owner)->get("/invoices/{$invoice->id}")->assertOk();

    $this->actingAs($admin)->get('/partners')->assertOk();
    $this->actingAs($admin)->get("/partners/{$partner->id}")->assertOk();
    $this->actingAs($admin)->get('/invoices')->assertOk();
    $this->actingAs($admin)->get("/invoices/{$invoice->id}")->assertOk();
});

it('admin tidak bisa membuat partner', function () {
    $tenant = Tenant::factory()->create();
    $admin = User::factory()->create([
        'role' => 'admin',
        'tenant_id' => $tenant->id,
        'email_verified_at' => now(),
    ]);

    $this->actingAs($admin)->post('/partners', [
        'name' => 'CV Baru',
        'type' => 'supplier',
    ])->assertForbidden();
});

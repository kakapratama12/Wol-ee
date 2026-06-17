<?php

use App\Models\Invoice;
use App\Models\Partner;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AgingService;
use App\Services\InvoiceService;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $tenant = Tenant::factory()->create();
    Sanctum::actingAs(User::factory()->create([
        'role' => 'owner',
        'tenant_id' => $tenant->id,
    ]));
});

it('membuat partner customer dan supplier', function () {
    $customer = $this->postJson('/api/partners', [
        'name' => 'Kantor Pak Joko',
        'type' => 'customer',
        'contact' => 'Pak Joko',
        'phone' => '081234567891',
    ]);

    $customer->assertCreated()
        ->assertJsonPath('name', 'Kantor Pak Joko')
        ->assertJsonPath('type', 'customer');

    $supplier = $this->postJson('/api/partners', [
        'name' => 'CV Tepung Sejahtera',
        'type' => 'supplier',
        'contact' => 'Pak Budi',
    ]);

    $supplier->assertCreated()->assertJsonPath('type', 'supplier');
});

it('menolak hapus partner dengan invoice outstanding', function () {
    $partner = Partner::create([
        'name' => 'Kantor Pak Joko',
        'type' => 'customer',
    ]);

    Invoice::create([
        'partner_id' => $partner->id,
        'invoice_number' => 'INV-'.now()->format('Ym').'-001',
        'amount' => 1000000,
        'paid_amount' => 0,
        'due_date' => now()->addDays(7),
        'status' => 'outstanding',
    ]);

    $response = $this->deleteJson("/api/partners/{$partner->id}");

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Partner masih punya invoice outstanding.');
});

it('membuat invoice dengan nomor otomatis', function () {
    $partner = Partner::create([
        'name' => 'Kantor Pak Joko',
        'type' => 'customer',
    ]);

    $response = $this->postJson('/api/invoices', [
        'partner_id' => $partner->id,
        'amount' => 5000000,
        'due_date' => now()->addDays(30)->toDateString(),
        'note' => 'Tagihan bulan Juni',
    ]);

    $response->assertCreated()
        ->assertJsonPath('invoice.amount', 5000000)
        ->assertJsonPath('invoice.status', 'outstanding');

    expect($response->json('invoice.invoice_number'))
        ->toStartWith('INV-'.now()->format('Ym').'-');
});

it('menolak invoice untuk partner supplier', function () {
    $partner = Partner::create([
        'name' => 'CV Tepung Sejahtera',
        'type' => 'supplier',
    ]);

    $response = $this->postJson('/api/invoices', [
        'partner_id' => $partner->id,
        'amount' => 5000000,
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    $response->assertStatus(422);
});

it('mencatat pembayaran parsial dan lunas', function () {
    $partner = Partner::create([
        'name' => 'Kantor Pak Joko',
        'type' => 'customer',
    ]);

    $invoice = Invoice::create([
        'partner_id' => $partner->id,
        'invoice_number' => 'INV-'.now()->format('Ym').'-001',
        'amount' => 5000000,
        'paid_amount' => 0,
        'due_date' => now()->addDays(7),
        'status' => 'outstanding',
    ]);

    $partial = $this->postJson("/api/invoices/{$invoice->id}/pay", [
        'amount' => 2000000,
    ]);

    $partial->assertOk()
        ->assertJsonPath('invoice.paid_amount', 2000000)
        ->assertJsonPath('invoice.remaining', 3000000)
        ->assertJsonPath('invoice.status', 'partial');

    $full = $this->postJson("/api/invoices/{$invoice->id}/pay", [
        'amount' => 3000000,
    ]);

    $full->assertOk()
        ->assertJsonPath('invoice.remaining', 0)
        ->assertJsonPath('invoice.status', 'paid');
});

it('menolak pembayaran melebihi tagihan', function () {
    $partner = Partner::create([
        'name' => 'Kantor Pak Joko',
        'type' => 'customer',
    ]);

    $invoice = Invoice::create([
        'partner_id' => $partner->id,
        'invoice_number' => 'INV-'.now()->format('Ym').'-001',
        'amount' => 5000000,
        'paid_amount' => 0,
        'due_date' => now()->addDays(7),
        'status' => 'outstanding',
    ]);

    $response = $this->postJson("/api/invoices/{$invoice->id}/pay", [
        'amount' => 6000000,
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Melebihi tagihan.');
});

it('menghitung aging per partner dan laporan global', function () {
    $partner = Partner::create([
        'name' => 'CV Maju Jaya',
        'type' => 'customer',
    ]);

    Invoice::create([
        'partner_id' => $partner->id,
        'invoice_number' => 'INV-'.now()->format('Ym').'-001',
        'amount' => 8000000,
        'paid_amount' => 0,
        'due_date' => now()->addDays(10),
        'status' => 'outstanding',
    ]);

    Invoice::create([
        'partner_id' => $partner->id,
        'invoice_number' => 'INV-'.now()->format('Ym').'-002',
        'amount' => 5000000,
        'paid_amount' => 0,
        'due_date' => now()->subDays(45),
        'status' => 'outstanding',
    ]);

    $detail = $this->getJson("/api/partners/{$partner->id}");
    $detail->assertOk()
        ->assertJsonPath('outstanding_invoices', 2)
        ->assertJsonPath('total_outstanding', 13000000)
        ->assertJsonPath('aging.current', 8000000)
        ->assertJsonPath('aging.1-2_months', 5000000);

    $report = $this->getJson('/api/reports/aging');
    $report->assertOk()
        ->assertJsonPath('summary.total_outstanding', 13000000)
        ->assertJsonPath('summary.total_partners', 1)
        ->assertJsonPath('by_aging.current', 8000000)
        ->assertJsonPath('by_aging.1-2_months', 5000000);
});

it('mengelompokkan invoice belum jatuh tempo ke bucket current', function () {
    $service = app(AgingService::class);

    expect($service->bucketForDueDate(Carbon::now()->addDays(5)))->toBe('current');
});

it('menghitung status invoice dari paid_amount', function () {
    $service = app(InvoiceService::class);

    expect($service->resolveStatus(5000000, 0))->toBe('outstanding')
        ->and($service->resolveStatus(5000000, 2000000))->toBe('partial')
        ->and($service->resolveStatus(5000000, 5000000))->toBe('paid')
        ->and($service->resolveStatus(5000000, 6000000))->toBe('paid');
});

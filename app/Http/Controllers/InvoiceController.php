<?php

namespace App\Http\Controllers;

use App\Http\Requests\Api\PayInvoiceRequest;
use App\Http\Requests\Api\StoreInvoiceRequest;
use App\Models\Invoice;
use App\Models\Partner;
use App\Services\InvoiceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Barryvdh\DomPDF\Facade\Pdf;
use InvalidArgumentException;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function index(Request $request): Response
    {
        $query = Invoice::query()
            ->with('partner')
            ->orderByDesc('created_at');

        // Filter: archived status
        $archived = $request->boolean('archived', false);
        if ($archived) {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at');
        }

        // Filter: status
        $status = $request->string('status');
        if ($status->isNotEmpty() && in_array($status->toString(), ['draft', 'outstanding', 'partial', 'paid'], true)) {
            $query->where('status', $status->toString());
        }

        $invoices = $query->get()->map(fn (Invoice $invoice) => [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'po_number' => $invoice->po_number,
            'partner' => $invoice->partner?->name,
            'amount' => (float) $invoice->amount,
            'paid_amount' => (float) $invoice->paid_amount,
            'remaining' => $invoice->remaining,
            'due_date' => $invoice->due_date->toDateString(),
            'status' => $invoice->status,
        ]);

        $customers = Partner::query()
            ->where('type', Partner::TYPE_CUSTOMER)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'customers' => $customers,
            'filters' => ['status' => $status->toString(), 'archived' => $archived],
        ]);
    }

    public function show(Invoice $invoice): Response
    {
        $invoice->load('partner', 'items', 'fees');
        $payments = [];

        if ((float) $invoice->paid_amount > 0) {
            $payments[] = [
                'paid_at' => $invoice->paid_at?->toDateString() ?? $invoice->updated_at->toDateString(),
                'amount' => (float) $invoice->paid_amount,
            ];
        }

        return Inertia::render('Invoices/Show', [
            'invoice' => $this->invoiceToArray($invoice),
            'payments' => $payments,
        ]);
    }

    public function edit(Invoice $invoice): Response
    {
        // Only draft and outstanding can be edited
        if (!in_array($invoice->status, ['draft', 'outstanding'], true)) {
            return back()->with('error', 'Invoice yang sudah dibayar sebagian/tidak bisa diedit.');
        }

        $invoice->load('partner', 'items', 'fees');
        $customers = Partner::query()
            ->where('type', Partner::TYPE_CUSTOMER)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Invoices/Edit', [
            'invoice' => $this->invoiceToArray($invoice),
            'customers' => $customers,
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        // Only draft and outstanding can be updated
        if (!in_array($invoice->status, ['draft', 'outstanding'], true)) {
            return back()->with('error', 'Invoice yang sudah dibayar sebagian/tidak bisa diedit.');
        }

        try {
            $validated = $request->validate([
                'partner_id' => ['required', 'exists:partners,id'],
                'due_date' => ['required', 'date'],
                'note' => ['nullable', 'string', 'max:1000'],
                'po_number' => ['nullable', 'string', 'max:100'],
                'amount' => ['nullable', 'numeric', 'min:0'],
                'items' => ['nullable', 'array', 'max:50'],
                'items.*.description' => ['required_with:items', 'string', 'max:255'],
                'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0', 'max:9999999'],
                'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0', 'max:99999999999'],
                'fees' => ['nullable', 'array', 'max:20'],
                'fees.*.name' => ['required_with:fees', 'string'],
                'fees.*.type' => ['required_with:fees', 'in:fixed,percentage'],
                'fees.*.value' => ['required_with:fees', 'numeric', 'min:0', 'max:99999999999'],
            ]);

            $partner = Partner::findOrFail($validated['partner_id']);
            if ($partner->type !== Partner::TYPE_CUSTOMER) {
                return back()->withErrors(['error' => 'Invoice hanya untuk partner tipe customer.'])->withInput();
            }

            $invoice->update([
                'partner_id' => $validated['partner_id'],
                'due_date' => $validated['due_date'],
                'note' => $validated['note'] ?? null,
                'po_number' => $validated['po_number'] ?? null,
            ]);

            if (!empty($validated['items']) && count($validated['items']) > 0) {
                $this->invoices->updateItems($invoice, $validated['items'], $validated['fees'] ?? []);
            } elseif (isset($validated['amount'])) {
                $invoice->update(['amount' => round((float) $validated['amount'], 2)]);
            }
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Invoice diperbarui.');
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        try {
            $invoice = $this->invoices->create($request->validated());
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Invoice dibuat.');
    }

    public function destroy(Invoice $invoice): RedirectResponse
    {
        // Only draft and outstanding can be deleted
        if (!in_array($invoice->status, ['draft', 'outstanding'], true)) {
            return back()->with('error', 'Invoice yang sudah dibayar tidak bisa dihapus.');
        }

        try {
            $invoice->items()->delete();
            $invoice->fees()->delete();
            $invoice->delete();
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('invoices.index')
            ->with('success', 'Invoice dihapus.');
    }

    public function archive(Invoice $invoice): RedirectResponse
    {
        // Only partial and paid can be archived
        if (!in_array($invoice->status, ['partial', 'paid'], true)) {
            return back()->with('error', 'Hanya invoice yang sudah dibayar sebagian/lunas yang bisa diarsipkan.');
        }

        $invoice->update(['archived_at' => now()]);

        return redirect()
            ->route('invoices.index')
            ->with('success', 'Invoice diarsipkan.');
    }

    public function pdf(Invoice $invoice)
    {
        try {
            $invoice->load('partner', 'items', 'fees');
            $tenant = $invoice->partner->tenant ?? auth()->user()->tenant;

            $pdf = Pdf::loadView('invoices.pdf', [
                'invoice' => $invoice,
                'tenant' => $tenant,
                'subtotal' => $invoice->subtotal,
            ]);

            return $pdf->download($invoice->invoice_number . '.pdf');
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    public function pdfPreview(Invoice $invoice)
    {
        $invoice->load('partner', 'items', 'fees');
        $tenant = $invoice->partner->tenant ?? auth()->user()->tenant;

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'tenant' => $tenant,
            'subtotal' => $invoice->subtotal,
        ]);

        return $pdf->stream($invoice->invoice_number . '.pdf');
    }

    public function kuitansi(Invoice $invoice)
    {
        $invoice->load('partner', 'items', 'fees');
        $tenant = $invoice->partner->tenant ?? auth()->user()->tenant;

        $pdf = Pdf::loadView('invoices.kuitansi', [
            'invoice' => $invoice,
            'tenant' => $tenant,
            'subtotal' => $invoice->subtotal,
        ]);

        return $pdf->download('Kuitansi-' . $invoice->invoice_number . '.pdf');
    }

    public function pay(PayInvoiceRequest $request, Invoice $invoice): RedirectResponse
    {
        if ($invoice->status === Invoice::STATUS_PAID) {
            return back()->with('error', 'Invoice sudah lunas.');
        }

        try {
            $this->invoices->recordPayment($invoice, $request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pembayaran tercatat.');
    }

    private function invoiceToArray(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'po_number' => $invoice->po_number,
            'partner_id' => $invoice->partner_id,
            'partner' => $invoice->partner?->name,
            'amount' => (float) $invoice->amount,
            'paid_amount' => (float) $invoice->paid_amount,
            'remaining' => $invoice->remaining,
            'due_date' => $invoice->due_date->toDateString(),
            'status' => $invoice->status,
            'note' => $invoice->note,
            'paid_at' => $invoice->paid_at?->toDateString(),
            'subtotal' => (float) $invoice->subtotal,
            'archived_at' => $invoice->archived_at?->toDateString(),
            'items' => $invoice->items->map(fn ($item) => [
                'id' => $item->id,
                'description' => $item->description,
                'quantity' => (float) $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'total' => (float) $item->total,
            ]),
            'fees' => $invoice->fees->map(fn ($fee) => [
                'id' => $fee->id,
                'name' => $fee->name,
                'type' => $fee->type,
                'value' => (float) $fee->value,
                'amount' => (float) $fee->amount,
            ]),
        ];
    }
}

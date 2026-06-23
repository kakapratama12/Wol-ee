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
            ->orderByDesc('due_date');

        $status = $request->string('status');
        if ($status->isNotEmpty() && in_array($status->toString(), ['outstanding', 'partial', 'paid'], true)) {
            $query->where('status', $status->toString());
        }

        $invoices = $query->get()->map(fn (Invoice $invoice) => [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'partner' => $invoice->partner?->name,
            'amount' => (float) $invoice->amount,
            'paid_amount' => (float) $invoice->paid_amount,
            'remaining' => $this->invoices->remainingAmount($invoice),
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
            'filters' => ['status' => $status->toString()],
        ]);
    }

    public function show(Invoice $invoice): Response
    {
        $invoice->load('partner', 'items');

        $payments = [];
        if ((float) $invoice->paid_amount > 0) {
            $payments[] = [
                'paid_at' => $invoice->paid_at?->toDateString() ?? $invoice->updated_at->toDateString(),
                'amount' => (float) $invoice->paid_amount,
            ];
        }

        return Inertia::render('Invoices/Show', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'partner_id' => $invoice->partner_id,
                'partner' => $invoice->partner?->name,
                'amount' => (float) $invoice->amount,
                'paid_amount' => (float) $invoice->paid_amount,
                'remaining' => $this->invoices->remainingAmount($invoice),
                'due_date' => $invoice->due_date->toDateString(),
                'status' => $invoice->status,
                'note' => $invoice->note,
                'paid_at' => $invoice->paid_at?->toDateString(),
                'items' => $invoice->items->map(fn ($item) => [
                    'id' => $item->id,
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'total' => (float) $item->total,
                ]),
            ],
            'payments' => $payments,
        ]);
    }

    public function edit(Invoice $invoice): Response
    {
        $invoice->load('partner', 'items');

        $customers = Partner::query()
            ->where('type', Partner::TYPE_CUSTOMER)
            ->orderBy('name')
            ->get(['id', 'name']);

        return Inertia::render('Invoices/Edit', [
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'partner_id' => $invoice->partner_id,
                'partner' => $invoice->partner?->name,
                'amount' => (float) $invoice->amount,
                'paid_amount' => (float) $invoice->paid_amount,
                'due_date' => $invoice->due_date->toDateString(),
                'status' => $invoice->status,
                'note' => $invoice->note,
                'items' => $invoice->items->map(fn ($item) => [
                    'id' => $item->id,
                    'description' => $item->description,
                    'quantity' => (float) $item->quantity,
                    'unit_price' => (float) $item->unit_price,
                    'total' => (float) $item->total,
                ]),
            ],
            'customers' => $customers,
        ]);
    }

    public function update(Request $request, Invoice $invoice): RedirectResponse
    {
        $validated = $request->validate([
            'partner_id' => ['required', 'exists:partners,id'],
            'due_date' => ['required', 'date'],
            'note' => ['nullable', 'string'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*.description' => ['required_with:items', 'string'],
            'items.*.quantity' => ['required_with:items', 'numeric', 'gt:0'],
            'items.*.unit_price' => ['required_with:items', 'numeric', 'min:0'],
        ]);

        // Validate partner is a customer
        $partner = Partner::findOrFail($validated['partner_id']);
        if ($partner->type !== Partner::TYPE_CUSTOMER) {
            return back()->with('error', 'Invoice hanya untuk partner tipe customer.');
        }

        $invoice->update([
            'partner_id' => $validated['partner_id'],
            'due_date' => $validated['due_date'],
            'note' => $validated['note'] ?? null,
        ]);

        // Update items if provided
        if (!empty($validated['items']) && count($validated['items']) > 0) {
            $this->invoices->updateItems($invoice, $validated['items']);
        } elseif (isset($validated['amount'])) {
            // If no items, update amount from request (only if no items were previously set)
            $invoice->update(['amount' => round((float) $validated['amount'], 2)]);
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Invoice diperbarui.');
    }

    public function store(StoreInvoiceRequest $request): RedirectResponse
    {
        try {
            $invoice = $this->invoices->create($request->validated());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('invoices.show', $invoice)
            ->with('success', 'Invoice dibuat.');
    }

    public function pdf(Invoice $invoice)
    {
        $invoice->load('partner', 'items');
        $tenant = $invoice->partner->tenant ?? auth()->user()->tenant;

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'tenant' => $tenant,
        ]);

        return $pdf->download($invoice->invoice_number . '.pdf');
    }

    public function pdfPreview(Invoice $invoice)
    {
        $invoice->load('partner', 'items');
        $tenant = $invoice->partner->tenant ?? auth()->user()->tenant;

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice,
            'tenant' => $tenant,
        ]);

        return $pdf->stream($invoice->invoice_number . '.pdf');
    }

    public function kuitansi(Invoice $invoice)
    {
        $invoice->load('partner', 'items');
        $tenant = $invoice->partner->tenant ?? auth()->user()->tenant;

        $pdf = Pdf::loadView('invoices.kuitansi', [
            'invoice' => $invoice,
            'tenant' => $tenant,
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
}

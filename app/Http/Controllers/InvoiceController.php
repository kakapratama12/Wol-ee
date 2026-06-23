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

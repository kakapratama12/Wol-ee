<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PayInvoiceRequest;
use App\Http\Requests\Api\StoreInvoiceRequest;
use App\Http\Requests\Api\UpdateInvoiceRequest;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoices) {}

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()
            ->with('partner')
            ->orderByDesc('due_date');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        $invoices = $query->get()->map(fn (Invoice $invoice) => $this->invoiceResource($invoice));

        return response()->json(['data' => $invoices]);
    }

    public function outstanding(): JsonResponse
    {
        $invoices = Invoice::query()
            ->with('partner')
            ->where('status', '!=', Invoice::STATUS_PAID)
            ->orderBy('due_date')
            ->get()
            ->map(fn (Invoice $invoice) => $this->invoiceResource($invoice));

        return response()->json(['data' => $invoices]);
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        try {
            $invoice = $this->invoices->create($request->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Invoice dibuat.',
            'invoice' => $this->invoiceResource($invoice->load('partner')),
        ], 201);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return response()->json($this->invoiceResource($invoice->load('partner')));
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        if ($invoice->status === Invoice::STATUS_PAID) {
            return response()->json(['message' => 'Invoice sudah lunas, tidak bisa diubah.'], 422);
        }

        $data = $request->validated();

        if (isset($data['amount'])) {
            $amount = round((float) $data['amount'], 2);
            if ($amount < (float) $invoice->paid_amount) {
                return response()->json([
                    'message' => 'Nominal tagihan tidak boleh kurang dari pembayaran yang sudah dicatat.',
                ], 422);
            }

            $data['amount'] = $amount;
            $data['status'] = $this->invoices->resolveStatus($amount, (float) $invoice->paid_amount);
        }

        $invoice->update($data);

        return response()->json([
            'message' => 'Invoice diperbarui.',
            'invoice' => $this->invoiceResource($invoice->fresh('partner')),
        ]);
    }

    public function pay(PayInvoiceRequest $request, Invoice $invoice): JsonResponse
    {
        if ($invoice->status === Invoice::STATUS_PAID) {
            return response()->json(['message' => 'Invoice sudah lunas.'], 422);
        }

        try {
            $result = $this->invoices->recordPayment($invoice, $request->validated());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $invoice = $result['invoice'];

        return response()->json([
            'message' => 'Pembayaran tercatat.',
            'invoice' => [
                'id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
                'amount' => (float) $invoice->amount,
                'paid_amount' => (float) $invoice->paid_amount,
                'remaining' => $result['remaining'],
                'status' => $invoice->status,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceResource(Invoice $invoice): array
    {
        return [
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
            'paid_at' => $invoice->paid_at?->toIso8601String(),
        ];
    }
}

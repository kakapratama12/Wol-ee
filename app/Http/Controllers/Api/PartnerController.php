<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePartnerRequest;
use App\Http\Requests\Api\UpdatePartnerRequest;
use App\Models\Invoice;
use App\Models\Partner;
use App\Services\AgingService;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    public function __construct(
        private readonly AgingService $aging,
        private readonly InvoiceService $invoices,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Partner::query()->orderBy('name');

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        $partners = $query->get()->map(fn (Partner $partner) => $this->partnerResource($partner));

        return response()->json(['data' => $partners]);
    }

    public function store(StorePartnerRequest $request): JsonResponse
    {
        $partner = Partner::create($request->validated());

        return response()->json($this->partnerResource($partner), 201);
    }

    public function show(Partner $partner): JsonResponse
    {
        $outstanding = $partner->invoices()
            ->where('status', '!=', Invoice::STATUS_PAID)
            ->get();

        $totalOutstanding = round($outstanding->sum(
            fn (Invoice $invoice) => $this->aging->remainingAmount($invoice)
        ), 2);

        return response()->json([
            ...$this->partnerResource($partner),
            'outstanding_invoices' => $outstanding->count(),
            'total_outstanding' => $totalOutstanding,
            'aging' => $this->aging->partnerAging($partner),
        ]);
    }

    public function update(UpdatePartnerRequest $request, Partner $partner): JsonResponse
    {
        $partner->update($request->validated());

        return response()->json($this->partnerResource($partner->fresh()));
    }

    public function destroy(Partner $partner): JsonResponse
    {
        $hasOutstanding = $partner->invoices()
            ->where('status', '!=', Invoice::STATUS_PAID)
            ->exists();

        if ($hasOutstanding) {
            return response()->json([
                'message' => 'Partner masih punya invoice outstanding.',
            ], 422);
        }

        $partner->delete();

        return response()->json(['message' => 'Partner dihapus.']);
    }

    public function aging(Partner $partner): JsonResponse
    {
        $outstanding = $partner->invoices()
            ->where('status', '!=', Invoice::STATUS_PAID)
            ->get();

        $totalOutstanding = round($outstanding->sum(
            fn (Invoice $invoice) => $this->aging->remainingAmount($invoice)
        ), 2);

        return response()->json([
            'partner_id' => $partner->id,
            'partner' => $partner->name,
            'total_outstanding' => $totalOutstanding,
            'outstanding_invoices' => $outstanding->count(),
            'aging' => $this->aging->partnerAging($partner),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function partnerResource(Partner $partner): array
    {
        return [
            'id' => $partner->id,
            'name' => $partner->name,
            'type' => $partner->type,
            'contact' => $partner->contact,
            'phone' => $partner->phone,
            'email' => $partner->email,
            'address' => $partner->address,
        ];
    }
}

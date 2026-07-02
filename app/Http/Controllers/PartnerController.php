<?php

namespace App\Http\Controllers;

use App\Http\Requests\Api\StorePartnerRequest;
use App\Http\Requests\Api\UpdatePartnerRequest;
use App\Models\Invoice;
use App\Models\Partner;
use App\Services\AgingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PartnerController extends Controller
{
    public function __construct(private readonly AgingService $aging) {}

    public function index(Request $request): Response
    {
        $query = Partner::query()->orderBy('name');

        $type = (string) $request->input('type', '');
        if ($type !== '' && in_array($type, ['customer', 'supplier'], true)) {
            $query->where('type', $type);
        }

        if ($request->filled('q')) {
            $search = '%'.mb_strtolower(trim($request->string('q'))).'%';
            $query->whereRaw('LOWER(name) LIKE ?', [$search]);
        }

        $partners = $query->get()->map(function (Partner $partner) {
            $outstanding = $partner->invoices()
                ->where('status', '!=', Invoice::STATUS_PAID)
                ->get();

            return [
                'id' => $partner->id,
                'name' => $partner->name,
                'type' => $partner->type,
                'contact' => $partner->contact,
                'phone' => $partner->phone,
                'email' => $partner->email,
                'address' => $partner->address,
                'outstanding_count' => $outstanding->count(),
                'total_outstanding' => round($outstanding->sum(
                    fn (Invoice $invoice) => $this->aging->remainingAmount($invoice)
                ), 2),
            ];
        });

        return Inertia::render('Partners/Index', [
            'partners' => $partners,
            'filters' => [
                'type' => $request->string('type'),
                'q' => $request->string('q'),
            ],
        ]);
    }

    public function show(Partner $partner): Response
    {
        $outstanding = $partner->invoices()
            ->where('status', '!=', Invoice::STATUS_PAID)
            ->orderBy('due_date')
            ->get()
            ->map(fn (Invoice $invoice) => $this->mapInvoiceSummary($invoice));

        $aging = $this->aging->partnerAging($partner);

        return Inertia::render('Partners/Show', [
            'partner' => [
                'id' => $partner->id,
                'name' => $partner->name,
                'type' => $partner->type,
                'contact' => $partner->contact,
                'phone' => $partner->phone,
                'email' => $partner->email,
                'address' => $partner->address,
            ],
            'outstandingInvoices' => $outstanding,
            'aging' => $aging,
            'totalOutstanding' => round(array_sum($aging), 2),
        ]);
    }

    public function store(StorePartnerRequest $request): RedirectResponse
    {
        Partner::create($request->validated());

        return back()->with('success', 'Partner ditambahkan.');
    }

    public function storeJson(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in([Partner::TYPE_CUSTOMER, Partner::TYPE_SUPPLIER])],
        ]);

        $partner = Partner::create([
            'name' => $request->input('name'),
            'type' => $request->input('type'),
        ]);

        return response()->json([
            'id' => $partner->id,
            'name' => $partner->name,
        ]);
    }

    public function update(UpdatePartnerRequest $request, Partner $partner): RedirectResponse
    {
        $partner->update($request->validated());

        return back()->with('success', 'Partner diperbarui.');
    }

    public function destroy(Partner $partner): RedirectResponse
    {
        $hasOutstanding = $partner->invoices()
            ->where('status', '!=', Invoice::STATUS_PAID)
            ->exists();

        if ($hasOutstanding) {
            return back()->with('error', 'Partner masih punya invoice outstanding.');
        }

        $partner->delete();

        return redirect()->route('partners.index')->with('success', 'Partner dihapus.');
    }

    /**
     * @return array<string, mixed>
     */
    private function mapInvoiceSummary(Invoice $invoice): array
    {
        $remaining = $this->aging->remainingAmount($invoice);
        $daysOverdue = $invoice->due_date->startOfDay()->diffInDays(now()->startOfDay(), false);

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'amount' => (float) $invoice->amount,
            'remaining' => $remaining,
            'due_date' => $invoice->due_date->toDateString(),
            'status' => $invoice->status,
            'days_label' => $daysOverdue < 0
                ? abs($daysOverdue).' hari lagi'
                : ($daysOverdue === 0 ? 'Jatuh tempo hari ini' : $daysOverdue.' hari'),
        ];
    }
}

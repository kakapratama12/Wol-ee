<?php

namespace App\Http\Controllers;

use App\Http\Requests\PayPaymentRequest;
use App\Http\Requests\StorePayableRequest;
use App\Models\Payable;
use App\Services\PayableService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PayableController extends Controller
{
    public function __construct(
        private readonly PayableService $payableService,
    ) {}

    /**
     * Daftar semua payables.
     */
    public function index(Request $request): Response
    {
        $query = Payable::with('partner')
            ->where('archived_at', null);

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $payables = $query->latest('due_date')->paginate(20);

        return Inertia::render('Payables/Index', [
            'payables' => $payables,
            'filters' => $request->only(['status']),
        ]);
    }

    /**
     * Detail satu payable.
     */
    public function show(Payable $payable): Response
    {
        $payable->load('partner', 'items');

        return Inertia::render('Payables/Show', [
            'payable' => $payable,
            'remaining' => $payable->remaining,
        ]);
    }

    /**
     * Buat payable baru.
     */
    public function store(StorePayableRequest $request): RedirectResponse
    {
        try {
            $this->payableService->create($request->validated());

            return redirect()->route('payables.index')
                ->with('success', 'Tagihan supplier berhasil dibuat.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    /**
     * Catat pembayaran (bisa partial).
     */
    public function pay(Payable $payable, PayPaymentRequest $request): RedirectResponse
    {
        try {
            $this->payableService->recordPayment($payable, $request->validated());

            $remaining = $payable->fresh()->remaining;

            $message = $remaining <= 0
                ? 'Tagihan berhasil dilunasi.'
                : 'Pembayaran berhasil dicatat. Sisa: Rp ' . number_format($remaining, 0, ',', '.');

            return redirect()->route('payables.show', $payable)
                ->with('success', $message);
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }

    /**
     * Archive payable.
     */
    public function archive(Payable $payable): RedirectResponse
    {
        try {
            $payable->update(['archived_at' => now()]);

            return redirect()->route('payables.index')
                ->with('success', 'Tagihan berhasil diarsipkan.');
        } catch (\Exception $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }
    }
}

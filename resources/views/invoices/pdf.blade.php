<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: sans-serif; font-size: 13px; color: #333; padding: 40px; background: #fff; }
        
        /* Header */
        .header { margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e5e7eb; }
        .header-table { width: 100%; border-collapse: collapse; }
        .header-table td { vertical-align: top; padding: 0; }
        .header-table td:first-child { width: 50%; }
        .header-table td:last-child { width: 50%; text-align: right; }
        .company-name { font-size: 22px; font-weight: 500; margin-bottom: 4px; }
        .company-detail { font-size: 13px; color: #6b7280; margin: 2px 0; }
        .invoice-title { font-size: 18px; font-weight: 500; margin-bottom: 4px; }
        .invoice-meta { font-size: 13px; color: #6b7280; margin: 2px 0; }
        .status-badge { display: inline-block; margin-top: 8px; padding: 3px 10px; border-radius: 4px; font-size: 12px; }
        .status-outstanding { background: #fef3c7; color: #92400e; }
        .status-partial { background: #dbeafe; color: #1e40af; }
        .status-paid { background: #d1fae5; color: #065f46; }
        
        /* Bill To */
        .bill-to { margin-bottom: 24px; }
        .section-label { font-size: 12px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
        .bill-to-name { font-size: 14px; font-weight: 500; margin-bottom: 2px; }
        .bill-to-detail { font-size: 13px; color: #6b7280; margin: 2px 0; }
        
        /* Table */
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        thead th { text-align: left; padding: 8px 0; font-weight: 500; color: #6b7280; border-bottom: 1px solid #e5e7eb; font-size: 12px; }
        thead th:last-child, thead th:nth-child(2), thead th:nth-child(3) { text-align: right; }
        tbody td { padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
        tbody td:last-child, tbody td:nth-child(2), tbody td:nth-child(3) { text-align: right; }
        .item-desc { font-weight: 500; }
        
        /* Summary */
        .summary-wrap { margin-top: 16px; }
        .summary-wrap table { width: 260px; margin-left: auto; border-collapse: collapse; }
        .summary-wrap td { padding: 5px 0; font-size: 13px; color: #6b7280; }
        .summary-wrap td:last-child { text-align: right; }
        .summary-wrap .fee td { padding-left: 12px; font-size: 12px; }
        .summary-wrap .total td { font-size: 15px; font-weight: 500; color: #111; border-top: 1px solid #e5e7eb; padding-top: 10px; margin-top: 4px; }
        
        /* Payment Info */
        .payment-info { margin-top: 24px; padding: 12px; background: #f9fafb; border-radius: 6px; font-size: 13px; }
        .payment-title { font-weight: 500; margin-bottom: 6px; }
        .payment-detail { color: #6b7280; }
        
        /* Footer */
        .footer { margin-top: 30px; font-size: 12px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <table class="header-table">
            <tr>
                {{-- Kiri: Logo + Nama + Detail --}}
                <td>
                    @if($tenant->logo)
                        @php
                            $logoPath = public_path('storage/logos/' . $tenant->id . '/' . $tenant->logo);
                        @endphp
                        @if(file_exists($logoPath))
                            <div style="margin-bottom: 8px;">
                                <img src="{{ $logoPath }}" alt="Logo" style="height: 96px; width: auto; object-fit: contain;">
                            </div>
                        @endif
                    @endif
                    <div class="company-name">{{ $tenant->name ?? '' }}</div>
                    @isset($tenant->address)
                        <div class="company-detail">{{ $tenant->address }}</div>
                    @endisset
                    @isset($tenant->phone)
                        <div class="company-detail">Telp: {{ $tenant->phone }}</div>
                    @endisset
                    @isset($tenant->email)
                        <div class="company-detail">{{ $tenant->email }}</div>
                    @endisset
                </td>
                {{-- Kanan: Invoice + Detail --}}
                <td>
                    @if($tenant->logo)
                        @php $logoPath2 = public_path('storage/logos/' . $tenant->id . '/' . $tenant->logo); @endphp
                        @if(file_exists($logoPath2))
                            <div style="height: 96px;"></div>
                        @endif
                    @endif
                    <div class="invoice-title">Invoice</div>
                    <div class="invoice-meta"># {{ $invoice->invoice_number }}</div>
                    <div class="invoice-meta" style="margin-top: 8px;">Tanggal: {{ $invoice->created_at->format('d M Y') }}</div>
                    <div class="invoice-meta">Jatuh tempo: {{ $invoice->due_date->format('d M Y') }}</div>
                    <span class="status-badge status-{{ $invoice->status }}">
                        @if($invoice->status === 'outstanding') Belum dibayar
                        @elseif($invoice->status === 'partial') Sebagian
                        @else Lunas
                        @endif
                    </span>
                </td>
            </tr>
        </table>
    </div>

    <div class="bill-to">
        <div class="section-label">Tagihan kepada</div>
        <div class="bill-to-name">{{ $invoice->partner->name ?? ' - ' }}</div>
        @if($invoice->partner?->address)
            <div class="bill-to-detail">{{ $invoice->partner->address }}</div>
        @endif
        @if($invoice->partner?->contact || $invoice->partner?->phone)
            <div class="bill-to-detail">{{ $invoice->partner->contact }}{!! $invoice->partner->contact && $invoice->partner->phone ? ' · ' : '' !!}{{ $invoice->partner->phone }}</div>
        @endif
        @if($invoice->partner?->email)
            <div class="bill-to-detail">{{ $invoice->partner->email }}</div>
        @endif
        @if($invoice->po_number)
            <div class="bill-to-detail" style="margin-top: 8px;"><strong>Nomor PO:</strong> {{ $invoice->po_number }}</div>
        @endif
    </div>

    @if($invoice->items->count() > 0)
    <table class="items">
        <thead>
            <tr>
                <th>Deskripsi</th>
                <th style="width: 60px;">Qty</th>
                <th style="width: 120px;">Harga satuan</th>
                <th style="width: 120px;">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
            <tr>
                <td><span class="item-desc">{{ $item->description }}</span></td>
                <td>{{ rtrim(rtrim(number_format((float) $item->quantity, 2, '.', ''), '0'), '.') }}</td>
                <td>{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                <td>{{ number_format($item->total, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Summary: Subtotal + Fees inline + Total --}}
    @php
        $totalFees = $invoice->fees->sum('amount');
    @endphp
    <div class="summary-wrap">
        <table>
            <tr>
                <td>Subtotal</td>
                <td>Rp {{ number_format($subtotal ?? $invoice->amount, 0, ',', '.') }}</td>
            </tr>
            @if($invoice->fees->count() > 0)
                @foreach($invoice->fees as $fee)
                <tr class="fee">
                    <td>{{ $fee->name }}{{ $fee->type === 'percentage' ? ' (' . number_format($fee->value, 0, ',', '.') . '%)' : '' }}</td>
                    <td>Rp {{ number_format($fee->amount, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            @endif
            <tr class="total">
                <td>Total</td>
                <td>Rp {{ number_format($invoice->amount, 0, ',', '.') }}</td>
            </tr>
        </table>
    </div>
    @else
    <div style="margin-bottom: 24px;">
        <div class="section-label">Tagihan</div>
        <div style="font-size: 22px; font-weight: 500;">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</div>
    </div>
    @endif

    <div class="payment-info">
        <div class="payment-title">Informasi pembayaran</div>
        @isset($tenant->bank_name)
            <div class="payment-detail"><strong>{{ $tenant->bank_name }}</strong></div>
        @endisset
        @isset($tenant->bank_account)
            <div class="payment-detail">No. Rekening: {{ $tenant->bank_account }}</div>
        @endisset
        @isset($tenant->bank_account_name)
            <div class="payment-detail">Atas Nama: {{ $tenant->bank_account_name }}</div>
        @endisset
        <div class="payment-detail" style="margin-top: 6px;">Mohon cantumkan nomor invoice {{ $invoice->invoice_number }} sebagai keterangan transfer.</div>
    </div>

    <div class="footer">Terima kasih atas kepercayaan Anda.</div>
</body>
</html>
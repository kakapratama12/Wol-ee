<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: sans-serif; font-size: 13px; color: #333; padding: 40px; background: #fff; }
        
        /* Header */
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 1px solid #e5e7eb; }
        .company-info { }
        .company-name { font-size: 22px; font-weight: 500; margin-bottom: 4px; }
        .company-detail { font-size: 13px; color: #6b7280; margin: 2px 0; }
        .invoice-right { text-align: right; }
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
        table { width: 100%; border-collapse: collapse; margin-bottom: 16px; }
        thead th { text-align: left; padding: 8px 0; font-weight: 500; color: #6b7280; border-bottom: 1px solid #e5e7eb; font-size: 12px; }
        thead th:last-child, thead th:nth-child(2), thead th:nth-child(3) { text-align: right; }
        tbody td { padding: 10px 0; border-bottom: 1px solid #e5e7eb; }
        tbody td:last-child, tbody td:nth-child(2), tbody td:nth-child(3) { text-align: right; }
        .item-desc { font-weight: 500; }
        
        /* Summary */
        .summary { display: flex; justify-content: flex-end; }
        .summary-box { width: 240px; }
        .summary-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 13px; color: #6b7280; }
        .summary-row.total { font-size: 15px; font-weight: 500; color: #111; border-top: 1px solid #e5e7eb; padding-top: 10px; margin-top: 4px; }
        
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
        <div class="company-info">
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
        </div>
        <div class="invoice-right">
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
        </div>
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
    </div>

    @if($invoice->items->count() > 0)
    <table>
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
                <td>{{ $item->quantity }}</td>
                <td>{{ number_format($item->unit_price, 0, ',', '.') }}</td>
                <td>{{ number_format($item->total, 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <div class="summary-box">
            <div class="summary-row">
                <span>Subtotal</span>
                <span>Rp {{ number_format($invoice->amount, 0, ',', '.') }}</span>
            </div>
            <div class="summary-row total">
                <span>Total</span>
                <span>Rp {{ number_format($invoice->amount, 0, ',', '.') }}</span>
            </div>
        </div>
    </div>
    @else
    <div style="margin-bottom: 24px;">
        <div class="section-label">Tagihan</div>
        <div style="font-size: 22px; font-weight: 500;">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</div>
    </div>
    @endif

    <div class="payment-info">
        <div class="payment-title">Informasi pembayaran</div>
        <div class="payment-detail">Mohon cantumkan nomor invoice {{ $invoice->invoice_number }} sebagai keterangan transfer.</div>
    </div>

    <div class="footer">Terima kasih atas kepercayaan Anda.</div>
</body>
</html>

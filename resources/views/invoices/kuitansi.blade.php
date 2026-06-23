<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Kuitansi {{ $invoice->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: sans-serif; font-size: 13px; color: #333; padding: 40px; background: #fff; }
        
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 2px solid #065f46; }
        .company-name { font-size: 22px; font-weight: 500; margin-bottom: 4px; }
        .company-detail { font-size: 13px; color: #6b7280; margin: 2px 0; }
        .receipt-right { text-align: right; }
        .receipt-title { font-size: 18px; font-weight: 500; color: #065f46; margin-bottom: 4px; }
        .receipt-meta { font-size: 13px; color: #6b7280; margin: 2px 0; }
        .paid-badge { display: inline-block; margin-top: 8px; padding: 3px 10px; background: #d1fae5; color: #065f46; border-radius: 4px; font-size: 12px; font-weight: 500; }
        
        .bill-to { margin-bottom: 24px; }
        .section-label { font-size: 12px; color: #9ca3af; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 6px; }
        .bill-to-name { font-size: 14px; font-weight: 500; margin-bottom: 2px; }
        .bill-to-detail { font-size: 13px; color: #6b7280; margin: 2px 0; }
        
        .amount-box { text-align: center; margin: 30px 0; padding: 20px; background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; }
        .amount-label { font-size: 12px; color: #6b7280; margin-bottom: 4px; }
        .amount-value { font-size: 28px; font-weight: 500; color: #065f46; }
        
        .details { margin-bottom: 24px; }
        .detail-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #f3f4f6; }
        .detail-label { color: #6b7280; }
        .detail-value { font-weight: 500; }
        
        .footer { margin-top: 40px; font-size: 12px; color: #9ca3af; text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="company-name">{{ $tenant->name }}</div>
        </div>
        <div class="receipt-right">
            <div class="receipt-title">KUITANSI</div>
            <div class="receipt-meta"># {{ $invoice->invoice_number }}-PY</div>
            <div class="receipt-meta" style="margin-top: 8px;">Tanggal: {{ $invoice->paid_at ? $invoice->paid_at->format('d M Y') : now()->format('d M Y') }}</div>
            <span class="paid-badge">LUNAS</span>
        </div>
    </div>

    <div class="bill-to">
        <div class="section-label">Diterima dari</div>
        <div class="bill-to-name">{{ $invoice->partner->name ?? ' - ' }}</div>
        @if($invoice->partner?->address)
            <div class="bill-to-detail">{{ $invoice->partner->address }}</div>
        @endif
        @if($invoice->partner?->phone)
            <div class="bill-to-detail">{{ $invoice->partner->phone }}</div>
        @endif
    </div>

    <div class="amount-box">
        <div class="amount-label">Total Dibayar</div>
        <div class="amount-value">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</div>
    </div>

    <div class="details">
        <div class="detail-row">
            <span class="detail-label">Nomor Invoice</span>
            <span class="detail-value">{{ $invoice->invoice_number }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Tanggal Invoice</span>
            <span class="detail-value">{{ $invoice->created_at->format('d M Y') }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Jatuh Tempo</span>
            <span class="detail-value">{{ $invoice->due_date->format('d M Y') }}</span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Tanggal Pembayaran</span>
            <span class="detail-value">{{ $invoice->paid_at ? $invoice->paid_at->format('d M Y') : now()->format('d M Y') }}</span>
        </div>
    </div>

    @if($invoice->note)
    <div style="margin-bottom: 24px; padding: 12px; background: #f9fafb; border-radius: 6px; font-size: 13px;">
        <div style="font-weight: 500; margin-bottom: 4px;">Catatan</div>
        <div style="color: #6b7280;">{{ $invoice->note }}</div>
    </div>
    @endif

    <div class="footer">
        <p>Dokumen ini merupakan bukti pembayaran yang sah.</p>
        <p>Digenerate pada {{ now()->format('d M Y H:i') }}</p>
    </div>
</body>
</html>

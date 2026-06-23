<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: sans-serif; font-size: 12px; color: #333; padding: 30px; }
        .header { display: flex; justify-content: space-between; margin-bottom: 30px; border-bottom: 2px solid #1A2B49; padding-bottom: 15px; }
        .company-name { font-size: 18px; font-weight: bold; color: #1A2B49; }
        .invoice-title { font-size: 24px; font-weight: bold; color: #1A2B49; text-align: right; }
        .invoice-number { color: #666; text-align: right; }
        .section { margin-bottom: 20px; }
        .section-title { font-weight: bold; color: #1A2B49; margin-bottom: 8px; font-size: 11px; text-transform: uppercase; }
        .info-grid { display: flex; gap: 40px; }
        .info-block { flex: 1; }
        .info-row { margin-bottom: 4px; }
        .info-label { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th { background: #1A2B49; color: white; padding: 8px 12px; text-align: left; font-size: 11px; }
        th:last-child, td:last-child { text-align: right; }
        td { padding: 8px 12px; border-bottom: 1px solid #eee; }
        .totals { margin-top: 20px; text-align: right; }
        .totals-row { margin-bottom: 4px; }
        .totals-label { display: inline-block; width: 120px; text-align: right; margin-right: 10px; }
        .totals-value { display: inline-block; width: 150px; text-align: right; font-weight: bold; }
        .grand-total { font-size: 14px; color: #1A2B49; border-top: 2px solid #1A2B49; padding-top: 8px; }
        .footer { margin-top: 40px; font-size: 10px; color: #999; text-align: center; }
        .status-badge { display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10px; font-weight: bold; }
        .status-outstanding { background: #FEF3C7; color: #92400E; }
        .status-partial { background: #DBEAFE; color: #1E40AF; }
        .status-paid { background: #D1FAE5; color: #065F46; }
    </style>
</head>
<body>
    <div class="header">
        <div>
            <div class="company-name">{{ $tenant->name }}</div>
        </div>
        <div>
            <div class="invoice-title">INVOICE</div>
            <div class="invoice-number">{{ $invoice->invoice_number }}</div>
        </div>
    </div>

    <div class="section">
        <div class="info-grid">
            <div class="info-block">
                <div class="section-title">Kepada</div>
                <div class="info-row"><strong>{{ $invoice->partner->name ?? '-' }}</strong></div>
                @if($invoice->partner?->address)
                    <div class="info-row">{{ $invoice->partner->address }}</div>
                @endif
                @if($invoice->partner?->phone)
                    <div class="info-row">Telp: {{ $invoice->partner->phone }}</div>
                @endif
                @if($invoice->partner?->email)
                    <div class="info-row">Email: {{ $invoice->partner->email }}</div>
                @endif
            </div>
            <div class="info-block">
                <div class="section-title">Detail Invoice</div>
                <div class="info-row"><span class="info-label">Tanggal:</span> {{ $invoice->created_at->format('d M Y') }}</div>
                <div class="info-row"><span class="info-label">Jatuh Tempo:</span> {{ $invoice->due_date->format('d M Y') }}</div>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="status-badge status-{{ $invoice->status }}">
                        @if($invoice->status === 'outstanding') Outstanding
                        @elseif($invoice->status === 'partial') Sebagian
                        @else Lunas
                        @endif
                    </span>
                </div>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Deskripsi</th>
                <th style="text-align:right">Jumlah (Rp)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Tagihan {{ $invoice->invoice_number }}</td>
                <td>{{ number_format($invoice->amount, 0, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    <div class="totals">
        <div class="totals-row">
            <span class="totals-label">Total:</span>
            <span class="totals-value">Rp {{ number_format($invoice->amount, 0, ',', '.') }}</span>
        </div>
        @if($invoice->paid_amount > 0)
        <div class="totals-row">
            <span class="totals-label">Terbayar:</span>
            <span class="totals-value" style="color: #065F46">- Rp {{ number_format($invoice->paid_amount, 0, ',', '.') }}</span>
        </div>
        @endif
        <div class="totals-row grand-total">
            <span class="totals-label">Sisa Tagihan:</span>
            <span class="totals-value">Rp {{ number_format($invoice->amount - $invoice->paid_amount, 0, ',', '.') }}</span>
        </div>
    </div>

    @if($invoice->note)
    <div class="section" style="margin-top: 20px;">
        <div class="section-title">Catatan</div>
        <p>{{ $invoice->note }}</p>
    </div>
    @endif

    <div class="footer">
        <p>Dokumen ini dicetak secara otomatis oleh {{ $tenant->name }}</p>
        <p>Digenerate pada {{ now()->format('d M Y H:i') }}</p>
    </div>
</body>
</html>

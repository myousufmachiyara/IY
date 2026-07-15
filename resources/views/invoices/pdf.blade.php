<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $invoice->invoice_no }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
    .header-table { width: 100%; margin-bottom: 20px; }
    .header-table td { vertical-align: top; }
    h1 { font-size: 20px; margin: 0 0 4px 0; color: #1d4ed8; }
    .muted { color: #666; }
    .box { border: 1px solid #ddd; padding: 10px; margin-bottom: 15px; }
    table.items { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
    table.items th, table.items td { border: 1px solid #ccc; padding: 6px 8px; text-align: left; }
    table.items th { background: #f3f4f6; }
    .text-end { text-align: right; }
    .totals-table { width: 45%; margin-left: auto; border-collapse: collapse; }
    .totals-table td { padding: 4px 8px; }
    .totals-table .grand { font-weight: bold; font-size: 14px; border-top: 2px solid #333; }
    .status-badge { display: inline-block; padding: 3px 10px; border-radius: 4px; font-size: 11px; text-transform: uppercase; }
    .status-paid { background: #d1e7dd; color: #0f5132; }
    .status-partial { background: #fff3cd; color: #7a5b00; }
    .status-issued { background: #cfe2ff; color: #084298; }
    footer.note { margin-top: 30px; font-size: 10px; color: #888; border-top: 1px solid #eee; padding-top: 10px; }
</style>
</head>
<body>

<table class="header-table">
    <tr>
        <td style="width:50%;">
            <h1>Vehicle Bidding &amp; Export Co.</h1>
            <div class="muted">Japan-based Auction &amp; Export Services</div>
        </td>
        <td style="width:50%; text-align:right;">
            <div style="font-size:16px; font-weight:bold;">INVOICE {{ $invoice->invoice_no }}</div>
            <div class="muted">Issued: {{ optional($invoice->issued_at)->format('d-m-Y') }}</div>
            <div><span class="status-badge status-{{ $invoice->status }}">{{ $invoice->status }}</span></div>
        </td>
    </tr>
</table>

<table class="header-table">
    <tr>
        <td style="width:50%;">
            <div class="box">
                <strong>Bill To</strong><br>
                {{ $invoice->customer->name }}<br>
                {{ $invoice->customer->phone }}<br>
                {{ $invoice->customer->email }}<br>
                {{ $invoice->customer->address }}
            </div>
        </td>
        <td style="width:50%;">
            <div class="box">
                <strong>Sales Agent</strong><br>
                {{ $invoice->agent->name ?? '—' }}<br><br>
                <strong>Payment Due Dates</strong><br>
                First 50%: {{ optional($invoice->due_first)->format('d-m-Y') ?? '—' }}<br>
                Final 50%: {{ optional($invoice->due_final)->format('d-m-Y') ?? 'To be set at shipment' }}
            </div>
        </td>
    </tr>
</table>

<table class="items">
    <thead>
        <tr>
            <th>Vehicle</th>
            <th>Chassis No.</th>
            <th>Year</th>
            <th class="text-end">Sale Price</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>{{ $invoice->vehicle->make }} {{ $invoice->vehicle->model }} {{ $invoice->vehicle->grade }}</td>
            <td>{{ $invoice->vehicle->chassis_no ?? '—' }}</td>
            <td>{{ $invoice->vehicle->year ?? '—' }}</td>
            <td class="text-end">¥{{ number_format($invoice->sale_price) }}</td>
        </tr>
    </tbody>
</table>

<table class="totals-table">
    <tr><td>Sale Price</td><td class="text-end">¥{{ number_format($invoice->sale_price) }}</td></tr>
    <tr><td>Settled / Discount</td><td class="text-end">−¥{{ number_format($invoice->settled_amount) }}</td></tr>
    <tr class="grand"><td>Total Payable</td><td class="text-end">¥{{ number_format($invoice->total_payable) }}</td></tr>
    <tr><td>Amount Paid</td><td class="text-end">¥{{ number_format($invoice->amount_paid) }}</td></tr>
    <tr class="grand"><td>Balance Due</td><td class="text-end">¥{{ number_format($invoice->balance()) }}</td></tr>
</table>

<footer class="note">
    Payment terms: 50% due within 15 days of invoice date (7 days + 8 days grace). Remaining 50% due 15 days + 7 days grace before vehicle arrival.
    The final port-clearance document is released only once 100% of the invoice amount is cleared.
</footer>

</body>
</html>
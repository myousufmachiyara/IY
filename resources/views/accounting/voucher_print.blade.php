<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $journalEntry->entry_no }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #222; }
    .center { text-align: center; }
    .brand { font-size: 22px; font-weight: bold; color: #2f6fa8; }
    .voucher-title { font-size: 16px; font-weight: bold; text-transform: uppercase; margin-top: 6px; letter-spacing: 1px; }
    table.meta { width: 100%; margin-top: 20px; }
    table.meta td { padding: 4px 0; font-size: 12px; }
    table.lines { width: 100%; border-collapse: collapse; margin-top: 20px; }
    table.lines th, table.lines td { border: 1px solid #333; padding: 8px; font-size: 12px; }
    table.lines th { background: #f3f3f3; text-align: left; }
    .amount { text-align: right; }
    .total-row td { font-weight: bold; border-top: 2px solid #333; }
    .sign-row { margin-top: 60px; display: flex; justify-content: space-between; }
    .sign-box { width: 200px; text-align: center; border-top: 1px solid #333; padding-top: 4px; font-size: 11px; }
</style>
</head>
<body>
    <div class="center">
        <div class="brand">IY AUTO TRADES</div>
        <div class="voucher-title">{{ $journalEntry->voucherLabel() }}</div>
    </div>

    <table class="meta">
        <tr>
            <td><strong>Voucher No:</strong> {{ $journalEntry->entry_no }}</td>
            <td style="text-align:right;"><strong>Date:</strong> {{ $journalEntry->date->format('d/m/Y') }}</td>
        </tr>
        <tr>
            <td colspan="2"><strong>Description:</strong> {{ $journalEntry->description }}</td>
        </tr>
        @if($journalEntry->is_backdated)
        <tr><td colspan="2"><em>Back-dated entry</em></td></tr>
        @endif
    </table>

    <table class="lines">
        <thead>
            <tr><th>Account</th><th class="amount">Debit (¥)</th><th class="amount">Credit (¥)</th></tr>
        </thead>
        <tbody>
            @foreach($journalEntry->lines as $line)
            <tr>
                <td>{{ $line->account->name }} <small style="color:#888;">({{ $line->account->code }})</small>@if($line->memo)<br><small>{{ $line->memo }}</small>@endif</td>
                <td class="amount">{{ $line->debit > 0 ? number_format($line->debit) : '' }}</td>
                <td class="amount">{{ $line->credit > 0 ? number_format($line->credit) : '' }}</td>
            </tr>
            @endforeach
            <tr class="total-row">
                <td>Total</td>
                <td class="amount">{{ number_format($journalEntry->totalDebit()) }}</td>
                <td class="amount">{{ number_format($journalEntry->totalCredit()) }}</td>
            </tr>
        </tbody>
    </table>

    <p style="margin-top:20px; font-size:11px; color:#666;">Prepared by: {{ $journalEntry->creator->name ?? '—' }}</p>

    <div class="sign-row">
        <div class="sign-box">Prepared By</div>
        <div class="sign-box">Approved By</div>
        <div class="sign-box">Received By</div>
    </div>
</body>
</html>
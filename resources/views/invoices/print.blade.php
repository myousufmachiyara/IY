<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>{{ $invoice_no }}</title>
<style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #222; }
    .center { text-align: center; }
    .logo { width: 140px; }
    .brand-name { font-size: 26px; font-weight: bold; color: #2f6fa8; }
    .brand-sub { font-size: 12px; color: #888; margin-top: 2px; }
    table.header-table { width: 100%; margin-top: 15px; }
    table.header-table td { vertical-align: top; font-size: 10.5px; }
    .co-address { font-weight: bold; line-height: 1.6; }
    .inv-meta { text-align: right; font-weight: bold; line-height: 1.8; }
    .to-label { color: #2f6fa8; font-size: 15px; font-weight: bold; margin-top: 18px; }
    table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
    table.items th, table.items td { border: 1px solid #333; padding: 5px 6px; font-size: 10px; text-align: left; }
    table.items th { background: #f3f3f3; }
    .total-label { font-weight: bold; }
    .total-value { text-align: right; }
    .bank-heading { color: #2f6fa8; font-size: 14px; font-weight: bold; text-decoration: underline; margin-top: 22px; }
    .bank-line { margin-top: 6px; }
    table.tier { width: 100%; border-collapse: collapse; margin-top: 8px; }
    table.tier td { border: 1px solid #333; padding: 4px 8px; font-size: 10px; }
    .tier-intro { margin-top: 14px; }
    ul.terms { margin-top: 10px; padding-left: 16px; }
    ul.terms li { margin-bottom: 8px; font-size: 10px; line-height: 1.4; }
    .thanks { text-align: center; color: #2f6fa8; font-weight: bold; font-size: 12px; margin-top: 20px; }
    .page-break { page-break-before: always; }
</style>
</head>
<body>
@include('invoices._print_body', compact('invoice_no', 'date', 'customer', 'vehicle', 'amount_label', 'total_label', 'amount'))
</body>
</html>
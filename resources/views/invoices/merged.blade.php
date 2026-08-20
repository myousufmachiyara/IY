<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Combined Invoice</title>
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
    .total-label { font-weight: bold; text-align: right; }
    .total-value { text-align: right; font-weight: bold; }
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
@foreach($groups as $group)
    @if(!$loop->first)<div class="page-break"></div>@endif
    @php $customer = $group['customer']; $invoices = $group['invoices']; $grandTotal = $invoices->sum('total_payable'); @endphp

    <div class="center">
        <img src="{{ public_path('assets/img/invoice-logo.png') }}" class="logo">
    </div>

    <table class="header-table">
        <tr>
            <td style="width:55%;">
                <span class="brand-name">IY AUTO TRADES</span><br>
                <span class="brand-sub">GLOBAL</span>
            </td>
            <td style="width:45%;" class="inv-meta">
                <span style="font-size:22px;">INVOICE</span><br>
                REF: IY/COMB/{{ $customer->id }}-{{ now()->format('Ymd') }}<br>
                DATE: {{ now()->format('d/m/Y') }}
            </td>
        </tr>
    </table>

    <table class="header-table">
        <tr>
            <td class="co-address">
                Office 16068 182-184 High Street North,<br>
                East Ham, London, United Kingdom<br>
                E6 2JA<br>
                +44 7367 065610
            </td>
        </tr>
    </table>

    <div class="to-label">TO:</div>
    <div style="margin-top:4px;">
        {{ $customer->name }}<br>
        @if($customer->consignee_name && $customer->consignee_name !== $customer->name)
            {{ $customer->consignee_name }}<br>
        @endif
        {{ $customer->address }}<br>
        {{ $customer->country }} {{ $customer->postal_code }}
    </div>

    <table class="items">
        <thead>
            <tr>
                <th>INVOICE NO.</th><th>BRAND</th><th>MODEL</th><th>FUEL</th><th>COLOR</th>
                <th>CHASSIS NO</th><th>ENGINE</th><th>YEAR</th><th>100% CNF PRICE</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoices as $inv)
                @php $bidDetails = $inv->vehicle->bid; @endphp
                <tr>
                    <td>{{ $inv->invoice_no }}</td>
                    <td>{{ $inv->vehicle->make }}</td>
                    <td>{{ $inv->vehicle->model }}</td>
                    <td>{{ $bidDetails->fuel_type ?? '—' }}</td>
                    <td>{{ $bidDetails->color ?? '—' }}</td>
                    <td>{{ $bidDetails->chassis_no ?? $inv->vehicle->chassis_no ?? '—' }}</td>
                    <td>{{ $bidDetails->engine ?? '—' }}</td>
                    <td>{{ $inv->vehicle->year }}</td>
                    <td>¥{{ number_format($inv->total_payable) }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="8" class="total-label">100% C&amp;F AMOUNT (COMBINED)</td>
                <td class="total-value">¥{{ number_format($grandTotal) }}</td>
            </tr>
        </tbody>
    </table>

    <div class="bank-heading">BANK DETAILS:</div>
    <div class="bank-line">Name: Iy Auto Trades Limited</div>
    <div class="bank-line">IBAN: GB85 TRWI 6084 6435 7246 03</div>
    <div class="bank-line">Swift/BIC: TRWIGB2LXXX</div>
    <div class="bank-line">Bank name and address: Wise Payments Limited, Worship Square, 65 Clifton Street, London, EC2A 4JE, United Kingdom</div>

    <div class="tier-intro">Auction Deposit: To participate in a bidding customer has to provide an auction security deposit as per the buying:</div>
    <table class="tier">
        <tr><td>0 – 1 million (¥)</td><td>¥100,000</td></tr>
        <tr><td>1 million – 5 million (¥)</td><td>25% C&amp;F AMOUNT</td></tr>
        <tr><td>5 million – 10 million (¥)</td><td>50% C&amp;F AMOUNT</td></tr>
        <tr><td>Above 10 million (¥)</td><td>100% C&amp;F AMOUNT</td></tr>
    </table>

    <div class="tier-intro">After winning the vehicle customer has to pay the initial payment of the vehicle within 7 days time period as per below criteria:</div>
    <table class="tier">
        <tr><td>under a million (¥)</td><td>50% C&amp;F AMOUNT</td></tr>
        <tr><td>under 5 million (¥)</td><td>60% C&amp;F AMOUNT</td></tr>
        <tr><td>Above 5 million (¥)</td><td>70% C&amp;F AMOUNT</td></tr>
        <tr><td>Above 10 million (¥)</td><td>100% C&amp;F AMOUNT</td></tr>
    </table>

    <ul class="terms">
        <li>If the customer has not paid the given initial payment, the company has the right to reserve the vehicle as company stock and resell it to any other customer, even at a loss; certain loss will be adjusted from the auction deposit, as it was taken for security purposes.</li>
        <li>The remaining payment must be paid within 15 days of the arrival of the vessel.</li>
        <li>If the 100% C&amp;F amount is not settled before two weeks of Arrival Company will resell the car, and the loss will be adjusted from the initial deposit of the particular vehicle.</li>
        <li>Payment Verification: IY Auto Trades reserves the right to withhold shipping documents until full payment is received and verified by our bank. Please note that a telegraphic transfer (TT) receipt alone may not serve as sufficient proof of payment.</li>
        <li>The price company shares is based on estimated calculations as per previous and current stats.</li>
        <li>Shipment charges are not fixed and may vary based on global conditions, marine traffic, and currency fluctuations.</li>
        <li>The company is not responsible for any damages to the vehicle due to a natural disaster. If the shipping line is insured, customers have to wait for compensation from the shipping line in such cases.</li>
        <li>For any delays in payments company will charge a penalty of ¥10,000 on a daily basis.</li>
        <li>For cancellation of any unit purchased by the customer, a forfeiture charge will be the same as the company service charges; expenses incurred for cancellation and re-auction will be shared with the customer to maintain transparency through business.</li>
        <li>If the customer disconnects from the company for 3 months after paying the security deposit, the company is not liable to pay its return if he returns.</li>
    </ul>

    <div class="thanks">THANKS FOR YOUR BUSINESS!</div>
@endforeach
</body>
</html>
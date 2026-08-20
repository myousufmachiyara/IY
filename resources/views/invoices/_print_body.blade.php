<div class="center">
    <img src="{{ public_path('assets/img/iy-auto-trades-logo.png') }}" class="logo">
</div>

<table class="header-table">
    <tr>
        <td style="width:55%;">
            <span class="brand-name">IY AUTO TRADES</span><br>
            <span class="brand-sub">GLOBAL</span>
        </td>
        <td style="width:45%;" class="inv-meta">
            <span style="font-size:22px;">INVOICE</span><br>
            INVOICE: {{ $invoice_no }}<br>
            DATE: {{ $date }}
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

@php
    // Fuel/Color/Engine are read LIVE from the bid record, not from Vehicle's own
    // copy — the bid is the single source of truth for these fields, so any
    // correction made on a bid (e.g. a backfill) is reflected on every invoice
    // automatically, with nothing to keep in sync on the Vehicle side.
    $bidDetails = $vehicle->bid;
@endphp

<table class="items">
    <thead>
        <tr>
            <th>NO.</th><th>BRAND</th><th>MODEL</th><th>FUEL</th><th>COLOR</th>
            <th>CHASSIS NO</th><th>ENGINE</th><th>YEAR</th><th>{{ $amount_label }}</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>1</td>
            <td>{{ $vehicle->make }}</td>
            <td>{{ $vehicle->model }}</td>
            <td>{{ $bidDetails->fuel_type ?? '—' }}</td>
            <td>{{ $bidDetails->color ?? '—' }}</td>
            <td>{{ $bidDetails->chassis_no ?? $vehicle->chassis_no ?? '—' }}</td>
            <td>{{ $bidDetails->engine ?? '—' }}</td>
            <td>{{ $vehicle->year }}</td>
            <td>¥{{ number_format($amount) }}</td>
        </tr>
        <tr>
            <td colspan="8" class="total-label">{{ $total_label }}</td>
            <td class="total-value">¥{{ number_format($amount) }}</td>
        </tr>
    </tbody>
</table>

<div class="bank-heading">BANK DETAILS:</div>
<div class="bank-line">Name: Iy Auto Trades Limited</div>
<div class="bank-line">IBAN: GB85 TRWI 6084 6435 7246 03</div>
<div class="bank-line">Swift/BIC: TRWIGB2LXXX</div>
<div class="bank-line">Bank name and address: Wise Payments Limited, Worship Square, 65 Clifton Street, London, EC2A 4JE, United Kingdom</div>

<div class="page-break"></div>

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

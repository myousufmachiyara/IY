@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<div><h2 class="text-dark"><strong id="currentDate"></strong></h2></div>

@php
    $blueDark = '#378ADD'; $blueLight = '#B5D4F4'; $blueMid = '#85B7EB'; $orange = '#f59e0b';
    function statCard($label, $value, $color, $route, $isMoney = true) {
        $v = $isMoney ? '¥' . number_format($value) : number_format($value);
        return "<div class=\"col-12 col-md-3 mb-2\"><section class=\"card card-featured-left card-featured-{$color}\"><div class=\"card-body\"><h3 class=\"text-dark\"><strong>{$label}</strong></h3><h2 class=\"m-0 text-{$color}\">{$v}</h2><div class=\"summary-footer\"><a class=\"text-{$color} text-uppercase\" href=\"{$route}\">View Details</a></div></div></section></div>";
    }
@endphp

{{-- ===================== OVERVIEW ===================== --}}
<h6 class="card title text-uppercase mt-2 mb-2">Overview</h6>
<div class="row">
    {!! statCard(auth()->user()->isSalesAgent() ? 'My Customers' : 'Total Customers', $stats['customers'], 'primary', route('customers.index'), false) !!}
    {!! statCard('Vehicles Bid', $stats['vehicles_bid'], 'tertiary', route('bids.index'), false) !!}
    {!! statCard('Vehicles Won', $stats['vehicles_won'], 'success', route('results.won'), false) !!}
    {!! statCard('Sales', $stats['sales'], 'primary', route('invoices.index')) !!}
</div>
<div class="row">
    {!! statCard('Customer Receivables', $stats['receivables'], 'danger', route('accounting.receivables')) !!}
    {!! statCard('Profit', $stats['profit'], 'success', route('accounting.profit_loss')) !!}
    @if($isPrivileged)
        {!! statCard('Pending Approvals', $stats['pending_approvals'], 'warning', route('approvals.index'), false) !!}
        {!! statCard('Vendor Payable', $stats['vendor_payable'], 'tertiary', route('accounting.payables')) !!}
    @endif
</div>

{{-- ===================== FINANCIAL POSITION ===================== --}}
@if($isPrivileged)
<h6 class="text-muted text-uppercase small mt-3 mb-2">Financial Position</h6>
<div class="row">
    {!! statCard('Cash & Bank Balance', $financial['cash_bank'], 'primary', route('accounting.cash_bank')) !!}
    {!! statCard('Customer Receivables', $financial['receivables'], 'tertiary', route('accounting.receivables')) !!}
    {!! statCard('Vendor Payables', $financial['payables'], 'success', route('accounting.payables')) !!}
</div>
<div class="row">
    {!! statCard('Operating Expenses This Month', $financial['op_expenses_month'], 'danger', route('expenses.index')) !!}
    {!! statCard('Customer Deposits Held', $financial['deposits_held'], 'success', route('customers.index', ['deposit_status' => 'approved'])) !!}
    <div class="col-12 col-md-3 mb-2">
        <section class="card card-featured-left card-featured-primary">
            <div class="card-body">
                <h3 class="text-dark"><strong>Net Cash Exposure</strong>
                    <i class="fa fa-info-circle text-muted" style="font-size:12px;" title="Cash + Expected Customer Collections − Vendor/Other Immediate Payables"></i>
                </h3>
                <h2 class="m-0 text-primary">¥{{ number_format($financial['net_cash_exposure']) }}</h2>
                <div class="summary-footer"><a class="text-primary text-uppercase" href="{{ route('accounting.trial_balance') }}">View Details</a></div>
            </div>
        </section>
    </div>
</div>

{{-- ===================== ACTION CENTRE + VEHICLE PIPELINE ===================== --}}
<div class="row mt-2">
    <div class="col-12 col-lg-6 mb-3">
        <section class="card h-100">
            <header class="card-header"><h3 class="card-title h6 mb-0">Action Centre</h3></header>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    @foreach($actionCentre as $item)
                    @php $dot = ['danger'=>'#e34948','warning'=>'#eda100','muted'=>'#c3c2b7'][$item['severity']]; @endphp
                    <tr>
                        <td style="width:20px;"><span style="display:inline-block;width:8px;height:8px;border-radius:50%;background:{{ $dot }};"></span></td>
                        <td>{{ $item['count'] }} {{ $item['label'] }}</td>
                        <td class="text-end"><a href="{{ $item['route'] }}" class="small">View Details</a></td>
                    </tr>
                    @endforeach
                </table>
            </div>
        </section>
    </div>
    <div class="col-12 col-lg-6 mb-3">
        <section class="card h-100">
            <header class="card-header"><h3 class="card-title h6 mb-0">Vehicle Pipeline</h3></header>
            <div class="card-body"><div style="position:relative; height:260px;"><canvas id="pipelineChart"></canvas></div></div>
        </section>
    </div>
</div>

{{-- ===================== REVENUE/PROFIT + SHIPMENT LOGISTICS ===================== --}}
<div class="row">
    <div class="col-12 col-lg-6 mb-3">
        <section class="card">
            <header class="card-header"><h3 class="card-title h6 mb-0">Revenue &amp; Profit</h3></header>
            <div class="card-body"><div style="position:relative; height:220px;"><canvas id="revProfitChart"></canvas></div></div>
        </section>
    </div>
    <div class="col-12 col-lg-6 mb-3">
        <section class="card">
            <header class="card-header"><h3 class="card-title h6 mb-0">Shipment / Logistics Panel</h3></header>
            <div class="card-body"><div style="position:relative; height:220px;"><canvas id="logisticsChart"></canvas></div></div>
        </section>
    </div>
</div>

{{-- ===================== AUCTION PERFORMANCE + TOP AUCTION HOUSES ===================== --}}
<div class="row">
    <div class="col-12 col-lg-6 mb-3">
        <section class="card">
            <header class="card-header"><h3 class="card-title h6 mb-0">Auction Performance</h3></header>
            <div class="card-body"><div style="position:relative; height:220px;"><canvas id="auctionPerfChart"></canvas></div></div>
        </section>
    </div>
    <div class="col-12 col-lg-6 mb-3">
        <section class="card">
            <header class="card-header"><h3 class="card-title h6 mb-0">Top Auction Houses</h3></header>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Auction House</th><th class="text-end">Bids</th><th class="text-end">Won</th><th class="text-end">Win Rate</th></tr></thead>
                    <tbody>
                        @forelse($topAuctionHouses as $r)
                        <tr><td>{{ $r['house'] }}</td><td class="text-end">{{ $r['bids'] }}</td><td class="text-end">{{ $r['won'] }}</td><td class="text-end">{{ $r['rate'] }}%</td></tr>
                        @empty<tr><td colspan="4" class="text-center text-muted py-3">No bid data yet.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

{{-- ===================== TOP AGENTS + TOP CUSTOMERS ===================== --}}
<div class="row">
    <div class="col-12 col-lg-6 mb-3">
        <section class="card">
            <header class="card-header"><h3 class="card-title h6 mb-0">Top-Performing Agents</h3></header>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Agent</th><th class="text-end">Bids</th><th class="text-end">Won</th><th class="text-end">Win%</th><th class="text-end">Sales</th><th class="text-end">Profit</th></tr></thead>
                    <tbody>
                        @forelse($topAgents as $a)
                        <tr><td>{{ $a['agent'] }}</td><td class="text-end">{{ $a['bids'] }}</td><td class="text-end">{{ $a['won'] }}</td><td class="text-end">{{ $a['rate'] }}%</td><td class="text-end">¥{{ number_format($a['sales']) }}</td><td class="text-end">¥{{ number_format($a['profit']) }}</td></tr>
                        @empty<tr><td colspan="6" class="text-center text-muted py-3">No agent data yet.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
    <div class="col-12 col-lg-6 mb-3">
        <section class="card">
            <header class="card-header"><h3 class="card-title h6 mb-0">Top Customers</h3></header>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>Customer</th><th class="text-end">Vehicles</th><th class="text-end">Sales</th><th class="text-end">Profit</th><th class="text-end">Outstanding</th></tr></thead>
                    <tbody>
                        @forelse($topCustomers as $c)
                        <tr><td>{{ $c['customer'] }}</td><td class="text-end">{{ $c['vehicles'] }}</td><td class="text-end">¥{{ number_format($c['sales']) }}</td><td class="text-end">¥{{ number_format($c['profit']) }}</td><td class="text-end">¥{{ number_format($c['outstanding']) }}</td></tr>
                        @empty<tr><td colspan="5" class="text-center text-muted py-3">No customer data yet.</td></tr>@endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

{{-- ===================== RECEIVABLES VS PAYABLE + VENDOR LEDGER ===================== --}}
<div class="row">
    <div class="col-12 col-lg-6 mb-3">
        <section class="card">
            <header class="card-header"><h3 class="card-title h6 mb-0">Receivables vs Payable</h3></header>
            <div class="card-body"><div style="position:relative; height:220px;"><canvas id="recvPayChart"></canvas></div></div>
        </section>
    </div>
    <div class="col-12 col-lg-6 mb-3">
        <section class="card">
            <header class="card-header"><h3 class="card-title h6 mb-0">Vendor Ledger</h3></header>
            <div class="card-body"><div style="position:relative; height:220px;"><canvas id="vendorLedgerChart"></canvas></div></div>
        </section>
    </div>
</div>
@endif

<script>
$(document).ready(function() {
    const now = new Date();
    const day = getDaySuffix(now.getDate());
    document.getElementById('currentDate').innerText = `${now.toLocaleString('en-GB', { weekday: 'long' })}, ${day} ${now.toLocaleString('en-GB', { month: 'long' })} ${now.getFullYear()}`;
});
function getDaySuffix(day) {
    if (day >= 11 && day <= 13) return day + 'th';
    switch (day % 10) { case 1: return day+'st'; case 2: return day+'nd'; case 3: return day+'rd'; default: return day+'th'; }
}

const blueDark = '#378ADD', blueLight = '#B5D4F4', blueMid = '#85B7EB', orange = '#f59e0b';
Chart.defaults.font.family = "'Poppins', sans-serif";
Chart.defaults.color = '#6b7280';
const noGridX = { grid: { display: false }, border: { display: false } };
const softGridY = { grid: { color: '#f1f5f9' }, border: { display: false }, ticks: { precision: 0 } };
function formatYen(n) { return '¥' + Math.round(n).toLocaleString('en-US'); }

// Custom plugin: draws the value at the end of each horizontal bar.
const barValuePlugin = {
    id: 'barValue',
    afterDatasetsDraw(chart) {
        if (!chart.config._showBarValues) return;
        const { ctx } = chart;
        chart.data.datasets.forEach((ds, i) => {
            const meta = chart.getDatasetMeta(i);
            meta.data.forEach((bar, idx) => {
                const value = ds.data[idx];
                ctx.save();
                ctx.font = "600 11px 'Poppins', sans-serif";
                ctx.fillStyle = '#fff';
                ctx.textAlign = 'right';
                ctx.textBaseline = 'middle';
                ctx.fillText(chart.config._moneyLabels ? formatYen(value) : value, bar.x - 8, bar.y);
                ctx.restore();
            });
        });
    }
};
Chart.register(barValuePlugin);

@if($isPrivileged)
new Chart(document.getElementById('pipelineChart'), {
    type: 'bar',
    data: { labels: @json($pipeline['labels']), datasets: [{ data: @json($pipeline['data']), backgroundColor: blueDark, borderRadius: 6, borderSkipped: false }] },
    options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: softGridY, y: noGridX } }
});

new Chart(document.getElementById('revProfitChart'), {
    type: 'bar',
    data: { labels: ['Revenue', 'Profit'], datasets: [{ data: [{{ $revenueProfit['revenue'] }}, {{ $revenueProfit['profit'] }}], backgroundColor: [blueDark, '#1baf7a'], borderRadius: 8, borderSkipped: false }] },
    options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: noGridX, y: { ...softGridY, ticks: { ...softGridY.ticks, callback: v => '¥'+(v/1000000)+'M' } } } }
});

new Chart(document.getElementById('logisticsChart'), {
    type: 'bar',
    data: { labels: @json($shipLogistics['labels']), datasets: [{ data: @json($shipLogistics['data']), backgroundColor: orange, borderRadius: 6, borderSkipped: false }] },
    options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: softGridY, y: noGridX } }
});

new Chart(document.getElementById('auctionPerfChart'), {
    type: 'bar',
    data: { labels: @json($auctionPerf['labels']), datasets: [{ data: @json($auctionPerf['data']), backgroundColor: [blueDark, '#1baf7a', '#e34948', orange], borderRadius: 6, borderSkipped: false }] },
    options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: noGridX, y: softGridY } }
});

new Chart(document.getElementById('recvPayChart'), {
    type: 'bar',
    data: { labels: ['Receivable', 'Payable'], datasets: [{ data: [{{ $financial['receivables'] }}, {{ $financial['payables'] }}], backgroundColor: [blueDark, blueLight], borderRadius: 8, borderSkipped: false }] },
    options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: noGridX, y: { ...softGridY, ticks: { ...softGridY.ticks, callback: v => '¥'+(v/1000000)+'M' } } } }
});

const vendorLedgerChart = new Chart(document.getElementById('vendorLedgerChart'), {
    type: 'bar',
    data: { labels: ['To Pay', 'Paid', 'Outstanding'], datasets: [{ data: [{{ $vendorLedger['to_pay'] }}, {{ $vendorLedger['paid'] }}, {{ $vendorLedger['outstanding'] }}], backgroundColor: [blueDark, blueMid, blueLight], borderRadius: 8, borderSkipped: false }] },
    options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { display: false }, y: noGridX } }
});
vendorLedgerChart.config._showBarValues = true;
vendorLedgerChart.config._moneyLabels = true;
vendorLedgerChart.update();
@endif
</script>
@endsection
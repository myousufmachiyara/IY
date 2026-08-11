@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
	<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

	<div>
		<h2 class="text-dark"><strong id="currentDate"></strong></h2>
	</div>

	<div class="row">
		@can('customers.index')
		<div class="col-12 col-md-3 mb-2">
			<section class="card card-featured-left card-featured-primary">
				<div class="card-body">
					<h3 class="text-dark"><strong>{{ auth()->user()->isSalesAgent() ? 'My Customers' : 'Total Customers' }}</strong></h3>
					<h2 class="m-0 text-primary">{{ number_format($stats['customers']) }}</h2>
					<div class="summary-footer"><a class="text-primary text-uppercase" href="{{ route('customers.index') }}">View Details</a></div>
				</div>
			</section>
		</div>
		@endcan

		@can('vehicle_requirement.index')
		<div class="col-12 col-md-3 mb-2">
			<section class="card card-featured-left card-featured-tertiary">
				<div class="card-body">
					<h3 class="text-dark"><strong>Open Requirements</strong></h3>
					<h2 class="m-0 text-tertiary">{{ number_format($stats['requirements']) }}</h2>
					<div class="summary-footer"><a class="text-tertiary text-uppercase" href="{{ route('vehicles.index') }}">View Details</a></div>
				</div>
			</section>
		</div>
		@endcan

		@can('bid_results.index')
		<div class="col-12 col-md-3 mb-2">
			<section class="card card-featured-left card-featured-warning">
				<div class="card-body">
					<h3 class="text-dark"><strong>Pending Bids</strong></h3>
					<h2 class="m-0 text-warning">{{ number_format($stats['pending_bids']) }}</h2>
					<div class="summary-footer"><a class="text-warning text-uppercase" href="{{ route('results.index') }}">View Details</a></div>
				</div>
			</section>
		</div>
		<div class="col-12 col-md-3 mb-2">
			<section class="card card-featured-left card-featured-success">
				<div class="card-body">
					<h3 class="text-dark"><strong>Won This Month</strong></h3>
					<h2 class="m-0 text-success">{{ number_format($stats['won_this_month']) }}</h2>
					<div class="summary-footer"><a class="text-success text-uppercase" href="{{ route('results.won') }}">View Details</a></div>
				</div>
			</section>
		</div>
		@endcan

		@can('invoices.index')
		<div class="col-12 col-md-3 mb-2">
			<section class="card card-featured-left card-featured-danger">
				<div class="card-body">
					<h3 class="text-dark"><strong>Outstanding Balance</strong></h3>
					<h2 class="m-0 text-danger">¥{{ number_format($stats['outstanding']) }}</h2>
					<div class="summary-footer"><a class="text-danger text-uppercase" href="{{ route('invoices.index') }}">View Details</a></div>
				</div>
			</section>
		</div>
		<div class="col-12 col-md-3 mb-2">
			<section class="card card-featured-left card-featured-danger">
				<div class="card-body">
					<h3 class="text-dark"><strong>Overdue Invoices</strong></h3>
					<h2 class="m-0 text-danger">{{ number_format($stats['overdue_invoices']) }}</h2>
					<div class="summary-footer"><a class="text-danger text-uppercase" href="{{ route('invoices.index') }}">View Details</a></div>
				</div>
			</section>
		</div>
		@endcan

		@if($isPrivileged)
		<div class="col-12 col-md-3 mb-2">
			<section class="card card-featured-left card-featured-warning">
				<div class="card-body">
					<h3 class="text-dark"><strong>Pending Deposit Approvals</strong></h3>
					<h2 class="m-0 text-warning">{{ number_format($stats['pending_deposits']) }}</h2>
					<div class="summary-footer"><a class="text-warning text-uppercase" href="{{ route('customers.index') }}">Review</a></div>
				</div>
			</section>
		</div>
		<div class="col-12 col-md-3 mb-2">
			<section class="card card-featured-left card-featured-tertiary">
				<div class="card-body">
					<h3 class="text-dark"><strong>Vendor Payable</strong></h3>
					<h2 class="m-0 text-tertiary">¥{{ number_format($stats['vendor_payable']) }}</h2>
					<div class="summary-footer"><a class="text-tertiary text-uppercase" href="{{ route('accounting.payables') }}">View Details</a></div>
				</div>
			</section>
		</div>
		@endif
	</div>

	{{-- ===================== CHARTS — everyone (auto-scoped to own data) ===================== --}}
	<div class="row mt-2">
		@can('invoices.index')
		<div class="col-12 col-lg-6 mb-3">
			<section class="card">
				<header class="card-header"><h3 class="card-title h6 mb-0">Revenue — Last 6 Months</h3></header>
				<div class="card-body"><canvas id="revenueChart" height="220"></canvas></div>
			</section>
		</div>
		@endcan

		@can('bid_results.index')
		<div class="col-12 col-lg-6 mb-3">
			<section class="card">
				<header class="card-header"><h3 class="card-title h6 mb-0">Bidding Funnel — Won vs Lost</h3></header>
				<div class="card-body"><canvas id="biddingChart" height="220"></canvas></div>
			</section>
		</div>
		@endcan

		@can('invoices.index')
		<div class="col-12 col-lg-4 mb-3">
			<section class="card">
				<header class="card-header"><h3 class="card-title h6 mb-0">Invoice Status</h3></header>
				<div class="card-body"><canvas id="invoiceStatusChart" height="220"></canvas></div>
			</section>
		</div>
		@endcan

		{{-- ===================== CHARTS — Super Admin / Accountant only ===================== --}}
		@if($isPrivileged)
		<div class="col-12 col-lg-4 mb-3">
			<section class="card">
				<header class="card-header"><h3 class="card-title h6 mb-0">Top Agents — Vehicles Won</h3></header>
				<div class="card-body"><canvas id="agentChart" height="220"></canvas></div>
			</section>
		</div>
		<div class="col-12 col-lg-4 mb-3">
			<section class="card">
				<header class="card-header"><h3 class="card-title h6 mb-0">Vendor Payable Exposure</h3></header>
				<div class="card-body"><canvas id="vendorChart" height="220"></canvas></div>
			</section>
		</div>
		@endif
	</div>

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

		const palette = ['#3b82f6','#10b981','#f59e0b','#ef4444','#8b5cf6','#14b8a6','#ec4899','#6366f1'];

		@can('invoices.index')
		new Chart(document.getElementById('revenueChart'), {
			type: 'line',
			data: {
				labels: @json($revenueChart['labels']),
				datasets: [{ label: 'Revenue (¥)', data: @json($revenueChart['data']), borderColor: '#3b82f6', backgroundColor: 'rgba(59,130,246,0.1)', fill: true, tension: 0.3 }]
			},
			options: { plugins: { legend: { display: false } }, scales: { y: { ticks: { callback: v => '¥' + v.toLocaleString() } } } }
		});
		@endcan

		@can('bid_results.index')
		new Chart(document.getElementById('biddingChart'), {
			type: 'bar',
			data: {
				labels: @json($biddingChart['labels']),
				datasets: [
					{ label: 'Won', data: @json($biddingChart['won']), backgroundColor: '#10b981' },
					{ label: 'Lost', data: @json($biddingChart['lost']), backgroundColor: '#ef4444' }
				]
			},
			options: { scales: { x: { stacked: true }, y: { stacked: true, ticks: { precision: 0 } } } }
		});
		@endcan

		@can('invoices.index')
		new Chart(document.getElementById('invoiceStatusChart'), {
			type: 'doughnut',
			data: { labels: @json($invoiceStatusChart['labels']), datasets: [{ data: @json($invoiceStatusChart['data']), backgroundColor: palette }] },
			options: { plugins: { legend: { position: 'bottom' } } }
		});
		@endcan

		@if($isPrivileged)
		new Chart(document.getElementById('agentChart'), {
			type: 'bar',
			data: { labels: @json($agentChart['labels']), datasets: [{ label: 'Vehicles Won', data: @json($agentChart['data']), backgroundColor: '#6366f1' }] },
			options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { ticks: { precision: 0 } } } }
		});

		new Chart(document.getElementById('vendorChart'), {
			type: 'bar',
			data: { labels: @json($vendorChart['labels']), datasets: [{ label: 'Payable (¥)', data: @json($vendorChart['data']), backgroundColor: '#f59e0b' }] },
			options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { ticks: { callback: v => '¥' + v.toLocaleString() } } } }
		});
		@endif
	</script>
@endsection
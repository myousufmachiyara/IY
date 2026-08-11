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
				<div class="card-body"><div style="position:relative; height:260px;"><canvas id="revenueChart"></canvas></div></div>
			</section>
		</div>
		@endcan

		@can('bid_results.index')
		<div class="col-12 col-lg-6 mb-3">
			<section class="card">
				<header class="card-header d-flex justify-content-between align-items-center">
					<h3 class="card-title h6 mb-0">Bidding Funnel — Won vs Lost</h3>
					<div class="d-flex gap-3" style="font-size:12px; color:#6b7280;">
						<span class="d-flex align-items-center gap-1"><span style="width:8px;height:8px;border-radius:2px;background:#185FA5;display:inline-block;"></span> Won</span>
						<span class="d-flex align-items-center gap-1"><span style="width:8px;height:8px;border-radius:2px;background:#B5D4F4;display:inline-block;"></span> Lost</span>
					</div>
				</header>
				<div class="card-body"><div style="position:relative; height:260px;"><canvas id="biddingChart"></canvas></div></div>
			</section>
		</div>
		@endcan

		@can('invoices.index')
		<div class="col-12 col-lg-4 mb-3">
			<section class="card">
				<header class="card-header"><h3 class="card-title h6 mb-0">Invoice Status</h3></header>
				<div class="card-body">
					<div class="d-flex align-items-center gap-3">
						<div style="position:relative; height:150px; width:150px; flex-shrink:0;"><canvas id="invoiceStatusChart"></canvas></div>
						<div style="font-size:13px;">
							<p class="mb-1 text-muted" style="font-size:12px;">Total Invoices</p>
							<p class="mb-3" style="font-size:26px; font-weight:600;">{{ $invoiceStatusChart['total'] }}</p>
							@foreach($invoiceStatusChart['labels'] as $i => $label)
								<p class="mb-1 d-flex align-items-center gap-2">
									<span style="width:8px;height:8px;border-radius:2px;background:{{ ['#E6F1FB','#B5D4F4','#85B7EB','#378ADD','#c3c2b7'][$i] }};display:inline-block;"></span>
									{{ $label }} <span class="text-muted">{{ $invoiceStatusChart['data'][$i] }}</span>
								</p>
							@endforeach
						</div>
					</div>
				</div>
			</section>
		</div>
		@endcan

		{{-- ===================== CHARTS — Super Admin / Accountant only ===================== --}}
		@if($isPrivileged)
		<div class="col-12 col-lg-4 mb-3">
			<section class="card">
				<header class="card-header"><h3 class="card-title h6 mb-0">Top Agents — Vehicles Won</h3></header>
				<div class="card-body"><div style="position:relative; height:220px;"><canvas id="agentChart"></canvas></div></div>
			</section>
		</div>
		<div class="col-12 col-lg-4 mb-3">
			<section class="card">
				<header class="card-header"><h3 class="card-title h6 mb-0">Vendor Payable Exposure</h3></header>
				<div class="card-body"><div style="position:relative; height:220px;"><canvas id="vendorChart"></canvas></div></div>
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

		// ── Option B: monochrome-blue two-tone bars, thick-ring donut with side
		// panel (built directly in the Blade markup above, not here), pill bars.
		const blueDark = '#378ADD', blueLight = '#B5D4F4', blueMid = '#85B7EB';

		Chart.defaults.font.family = "'Poppins', sans-serif";
		Chart.defaults.color = '#6b7280';
		Chart.defaults.plugins.tooltip.backgroundColor = '#fff';
		Chart.defaults.plugins.tooltip.titleColor = '#222b36';
		Chart.defaults.plugins.tooltip.bodyColor = '#222b36';
		Chart.defaults.plugins.tooltip.borderColor = '#e5e7eb';
		Chart.defaults.plugins.tooltip.borderWidth = 1;
		Chart.defaults.plugins.tooltip.cornerRadius = 10;
		Chart.defaults.plugins.tooltip.padding = 12;

		const noGridX = { grid: { display: false }, border: { display: false } };
		const softGridY = { grid: { color: '#f1f5f9' }, border: { display: false }, ticks: { precision: 0 } };

		@can('invoices.index')
		new Chart(document.getElementById('revenueChart'), {
			type: 'bar',
			data: {
				labels: @json($revenueChart['labels']),
				datasets: [{
					label: 'Revenue (¥)',
					data: @json($revenueChart['data']),
					backgroundColor: @json($revenueChart['labels']).map((_, i) => i % 2 === 0 ? blueDark : blueLight),
					borderRadius: 6, borderSkipped: false,
				}]
			},
			options: {
				maintainAspectRatio: false,
				plugins: { legend: { display: false } },
				scales: { x: noGridX, y: { ...softGridY, ticks: { ...softGridY.ticks, callback: v => '¥' + (v/1000) + 'k' } } },
				barPercentage: 0.55,
			}
		});
		@endcan

		@can('bid_results.index')
		new Chart(document.getElementById('biddingChart'), {
			type: 'bar',
			data: {
				labels: @json($biddingChart['labels']),
				datasets: [
					{ label: 'Won', data: @json($biddingChart['won']), backgroundColor: blueDark, borderRadius: 8, borderSkipped: false },
					{ label: 'Lost', data: @json($biddingChart['lost']), backgroundColor: blueLight, borderRadius: 8, borderSkipped: false }
				]
			},
			options: {
				maintainAspectRatio: false,
				scales: { x: noGridX, y: softGridY },
				plugins: { legend: { display: false } },
				barPercentage: 0.5, categoryPercentage: 0.6,
			}
		});
		@endcan

		@can('invoices.index')
		new Chart(document.getElementById('invoiceStatusChart'), {
			type: 'doughnut',
			data: { labels: @json($invoiceStatusChart['labels']), datasets: [{ data: @json($invoiceStatusChart['data']), backgroundColor: ['#E6F1FB','#B5D4F4','#85B7EB','#378ADD','#c3c2b7'], borderWidth: 3, borderColor: '#fff' }] },
			options: { maintainAspectRatio: false, cutout: '65%', plugins: { legend: { display: false } } },
		});
		@endcan

		@if($isPrivileged)
		new Chart(document.getElementById('agentChart'), {
			type: 'bar',
			data: { labels: @json($agentChart['labels']), datasets: [{ label: 'Vehicles Won', data: @json($agentChart['data']), backgroundColor: blueDark, borderRadius: 20, borderSkipped: false }] },
			options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: softGridY, y: noGridX }, barPercentage: 0.5 }
		});

		new Chart(document.getElementById('vendorChart'), {
			type: 'bar',
			data: { labels: @json($vendorChart['labels']), datasets: [{ label: 'Payable (¥)', data: @json($vendorChart['data']), backgroundColor: blueMid, borderRadius: 20, borderSkipped: false }] },
			options: {
				indexAxis: 'y', maintainAspectRatio: false,
				plugins: { legend: { display: false } },
				scales: { x: { ...softGridY, ticks: { callback: v => '¥' + (v/1000) + 'k' } }, y: noGridX },
				barPercentage: 0.5,
			}
		});
		@endif
	</script>
@endsection
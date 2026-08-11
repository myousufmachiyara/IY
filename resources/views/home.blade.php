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
				<header class="card-header"><h3 class="card-title h6 mb-0">Bidding Funnel — Won vs Lost</h3></header>
				<div class="card-body"><div style="position:relative; height:260px;"><canvas id="biddingChart"></canvas></div></div>
			</section>
		</div>
		@endcan

		@can('invoices.index')
		<div class="col-12 col-lg-4 mb-3">
			<section class="card">
				<header class="card-header"><h3 class="card-title h6 mb-0">Invoice Status</h3></header>
				<div class="card-body"><div style="position:relative; height:260px;"><canvas id="invoiceStatusChart"></canvas></div></div>
			</section>
		</div>
		@endcan

		{{-- ===================== CHARTS — Super Admin / Accountant only ===================== --}}
		@if($isPrivileged)
		<div class="col-12 col-lg-4 mb-3">
			<section class="card">
				<header class="card-header"><h3 class="card-title h6 mb-0">Top Agents — Vehicles Won</h3></header>
				<div class="card-body"><div style="position:relative; height:260px;"><canvas id="agentChart"></canvas></div></div>
			</section>
		</div>
		<div class="col-12 col-lg-4 mb-3">
			<section class="card">
				<header class="card-header"><h3 class="card-title h6 mb-0">Vendor Payable Exposure</h3></header>
				<div class="card-body"><div style="position:relative; height:260px;"><canvas id="vendorChart"></canvas></div></div>
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

		// ── Shared modern-dashboard styling: soft colors, curved lines, rounded/pill
		// bars, clean white tooltips, and a small custom plugin for doughnut center text.
		const palette = { blue:'#3b82f6', green:'#10b981', amber:'#f59e0b', red:'#ef4444', violet:'#8b5cf6', teal:'#14b8a6', pink:'#ec4899', indigo:'#6366f1' };

		Chart.defaults.font.family = "'Poppins', sans-serif";
		Chart.defaults.color = '#6b7280';
		Chart.defaults.plugins.legend.labels.usePointStyle = true;
		Chart.defaults.plugins.legend.labels.pointStyle = 'circle';
		Chart.defaults.plugins.tooltip.backgroundColor = '#fff';
		Chart.defaults.plugins.tooltip.titleColor = '#222b36';
		Chart.defaults.plugins.tooltip.bodyColor = '#222b36';
		Chart.defaults.plugins.tooltip.borderColor = '#e5e7eb';
		Chart.defaults.plugins.tooltip.borderWidth = 1;
		Chart.defaults.plugins.tooltip.cornerRadius = 10;
		Chart.defaults.plugins.tooltip.padding = 12;
		Chart.defaults.plugins.tooltip.boxPadding = 4;

		function fadeGradient(canvasId, hex) {
			const ctx = document.getElementById(canvasId).getContext('2d');
			const g = ctx.createLinearGradient(0, 0, 0, 260);
			const rgb = hex.match(/\w\w/g).map(x => parseInt(x, 16)).join(',');
			g.addColorStop(0, `rgba(${rgb},0.35)`);
			g.addColorStop(1, `rgba(${rgb},0)`);
			return g;
		}

		const centerTextPlugin = {
			id: 'centerText',
			beforeDraw(chart) {
				if (chart.config.type !== 'doughnut' || !chart.config._showCenterText) return;
				const { ctx, chartArea: { left, right, top, bottom } } = chart;
				const cx = (left + right) / 2, cy = (top + bottom) / 2;
				const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
				ctx.save();
				ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
				ctx.font = "700 24px 'Poppins', sans-serif"; ctx.fillStyle = '#222b36';
				ctx.fillText(total, cx, cy - 10);
				ctx.font = "11px 'Poppins', sans-serif"; ctx.fillStyle = '#8a8a8a';
				ctx.fillText('Total Invoices', cx, cy + 14);
				ctx.restore();
			}
		};
		Chart.register(centerTextPlugin);

		const noGridX = { grid: { display: false }, border: { display: false } };
		const softGridY = { grid: { color: '#f1f5f9' }, border: { display: false }, ticks: { precision: 0 } };

		@can('invoices.index')
		new Chart(document.getElementById('revenueChart'), {
			type: 'line',
			data: {
				labels: @json($revenueChart['labels']),
				datasets: [{
					label: 'Revenue (¥)',
					data: @json($revenueChart['data']),
					borderColor: palette.blue,
					backgroundColor: fadeGradient('revenueChart', '3b82f6'),
					fill: true, tension: 0.4, borderWidth: 3,
					pointRadius: 0, pointHoverRadius: 6, pointHoverBackgroundColor: palette.blue, pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2,
				}]
			},
			options: {
				maintainAspectRatio: false,
				plugins: { legend: { display: false } },
				scales: { x: noGridX, y: { ...softGridY, ticks: { ...softGridY.ticks, callback: v => '¥' + (v/1000) + 'k' } } },
				interaction: { intersect: false, mode: 'index' },
			}
		});
		@endcan

		@can('bid_results.index')
		new Chart(document.getElementById('biddingChart'), {
			type: 'bar',
			data: {
				labels: @json($biddingChart['labels']),
				datasets: [
					{ label: 'Won', data: @json($biddingChart['won']), backgroundColor: palette.green, borderRadius: 6, borderSkipped: false },
					{ label: 'Lost', data: @json($biddingChart['lost']), backgroundColor: palette.red, borderRadius: 6, borderSkipped: false }
				]
			},
			options: {
				maintainAspectRatio: false,
				scales: {
					x: { ...noGridX, stacked: true },
					y: { ...softGridY, stacked: true }
				},
				plugins: { legend: { position: 'bottom' } },
				barPercentage: 0.5, categoryPercentage: 0.6,
			}
		});
		@endcan

		@can('invoices.index')
		new Chart(document.getElementById('invoiceStatusChart'), {
			type: 'doughnut',
			data: { labels: @json($invoiceStatusChart['labels']), datasets: [{ data: @json($invoiceStatusChart['data']), backgroundColor: Object.values(palette), borderWidth: 3, borderColor: '#fff' }] },
			options: { maintainAspectRatio: false, cutout: '72%', plugins: { legend: { position: 'bottom' } } },
			_showCenterText: true,
		});
		@endcan

		@if($isPrivileged)
		new Chart(document.getElementById('agentChart'), {
			type: 'bar',
			data: { labels: @json($agentChart['labels']), datasets: [{ label: 'Vehicles Won', data: @json($agentChart['data']), backgroundColor: palette.indigo, borderRadius: 20, borderSkipped: false }] },
			options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: softGridY, y: noGridX }, barPercentage: 0.5 }
		});

		new Chart(document.getElementById('vendorChart'), {
			type: 'bar',
			data: { labels: @json($vendorChart['labels']), datasets: [{ label: 'Payable (¥)', data: @json($vendorChart['data']), backgroundColor: palette.amber, borderRadius: 20, borderSkipped: false }] },
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
@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
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
	</script>
@endsection
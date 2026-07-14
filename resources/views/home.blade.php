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
				<div class="card-body icon-container data-container">
					<h3 class="amount text-dark"><strong>{{ auth()->user()->isSalesAgent() ? 'My Customers' : 'Total Customers' }}</strong></h3>
					<h2 class="amount m-0 text-primary">
						<strong data-value="">{{ number_format($stats['customers']) }}</strong>
					</h2>
					<div class="summary-footer">
						<a class="text-primary text-uppercase" href="{{ route('customers.index') }}">View Details</a>
					</div>
				</div>
			</section>
		</div>
		@endcan

		@can('vehicles.index')
		<div class="col-12 col-md-3 mb-2">
			<section class="card card-featured-left card-featured-tertiary">
				<div class="card-body icon-container data-container">
					<h3 class="amount text-dark"><strong>In Bidding</strong></h3>
					<h2 class="amount m-0 text-tertiary">
						<strong data-value="">{{ number_format($stats['in_bidding']) }}</strong>
						<span class="title text-end text-dark h6"> vehicles</span>
					</h2>
					<div class="summary-footer">
						<a class="text-tertiary text-uppercase" href="{{ route('vehicles.index') }}">View Details</a>
					</div>
				</div>
			</section>
		</div>
		@endcan

		@can('results.index')
		<div class="col-12 col-md-3 mb-2">
			<section class="card card-featured-left card-featured-success">
				<div class="card-body icon-container data-container">
					<h3 class="amount text-dark"><strong>Won This Month</strong></h3>
					<h2 class="amount m-0 text-success">
						<strong data-value="">{{ number_format($stats['won_this_month']) }}</strong>
						<span class="title text-end text-dark h6"> vehicles</span>
					</h2>
					<div class="summary-footer">
						<a class="text-success text-uppercase" href="{{ route('results.index') }}">View Details</a>
					</div>
				</div>
			</section>
		</div>
		@endcan

		@can('invoices.index')
		<div class="col-12 col-md-3 mb-2">
			<section class="card card-featured-left card-featured-danger">
				<div class="card-body icon-container data-container">
					<h3 class="amount text-dark"><strong>Outstanding Balance</strong></h3>
					<h2 class="amount m-0 text-danger">
						<strong data-value="">¥{{ number_format($stats['outstanding']) }}</strong>
					</h2>
					<div class="summary-footer">
						<a class="text-danger text-uppercase" href="{{ route('invoices.index') }}">View Details</a>
					</div>
				</div>
			</section>
		</div>
		@endcan
	</div>

	<div class="alert alert-light border mt-2">
		<i class="fa fa-info-circle text-muted"></i>
		<span class="text-muted">Temporary dashboard — full analytics, charts, and activity feeds come once all modules and reports are built.</span>
	</div>

	<script>
		$(document).ready(function() {
			const now = new Date();
			const day = getDaySuffix(now.getDate());
			const formattedDate = `${now.toLocaleString('en-GB', { weekday: 'long' })}, ${day} ${now.toLocaleString('en-GB', { month: 'long' })} ${now.getFullYear()}`;
			document.getElementById('currentDate').innerText = formattedDate;
		});

		function getDaySuffix(day) {
			if (day >= 11 && day <= 13) {
				return day + 'th';
			}
			switch (day % 10) {
				case 1: return day + 'st';
				case 2: return day + 'nd';
				case 3: return day + 'rd';
				default: return day + 'th';
			}
		}
	</script>
@endsection
@extends('layouts.app')
@section('title', 'Accounting | Cash Book')
@section('content')
<div class="row"><div class="col"><section class="card">
    <header class="card-header"><h2 class="card-title">Accounting</h2></header>
    @include('accounting._tabs', ['active' => 'cash_book'])
    @include('accounting._money_book', ['route' => 'accounting.cash_book'])
</section></div></div>
@endsection

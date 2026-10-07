@extends('layouts.app')
@section('title', 'Accounting | Cash & Bank Book')
@section('content')
<div class="row"><div class="col"><section class="card">
    <header class="card-header"><h2 class="card-title">Accounting</h2></header>
    @include('accounting._tabs', ['active' => 'cash_bank'])
    @include('accounting._money_book', ['route' => 'accounting.cash_bank'])
</section></div></div>
@endsection

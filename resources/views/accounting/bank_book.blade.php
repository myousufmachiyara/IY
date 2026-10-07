@extends('layouts.app')
@section('title', 'Accounting | Bank Book')
@section('content')
<div class="row"><div class="col"><section class="card">
    <header class="card-header"><h2 class="card-title">Accounting</h2></header>
    @include('accounting._tabs', ['active' => 'bank_book'])
    @include('accounting._money_book', ['route' => 'accounting.bank_book'])
</section></div></div>
@endsection

@extends('layouts.app')
@section('title', 'New Role')
@section('content')
<div class="row"><div class="col">
    <section class="card">
        <header class="card-header"><h2 class="card-title">New Role</h2></header>
        <div class="card-body">
            <form method="POST" action="{{ route('roles.store') }}">
                @csrf
                @include('roles._form')
            </form>
        </div>
    </section>
</div></div>
@endsection
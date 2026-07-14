@extends('layouts.app')
@section('title', 'Edit Role')
@section('content')
<div class="row"><div class="col">
    <section class="card">
        <header class="card-header"><h2 class="card-title">Edit Role: {{ \Illuminate\Support\Str::headline($role->name) }}</h2></header>
        <div class="card-body">
            <form method="POST" action="{{ route('roles.update', $role) }}">
                @csrf @method('PUT')
                @include('roles._form')
            </form>
        </div>
    </section>
</div></div>
@endsection
@extends('layouts.app')

@section('title', 'Roles & Permissions')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

            <header class="card-header">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <h2 class="card-title">Roles &amp; Permissions</h2>
                    @can('user_roles.create')
                        <a href="{{ route('roles.create') }}" class="btn btn-primary btn-sm">+ New Role</a>
                    @endcan
                </div>
            </header>
            <div class="card-body">
                <div class="table-scroll">
                    <table class="table table-bordered table-striped mb-0" id="datatable-default">
                        <thead><tr><th>Role</th><th>Permissions</th><th>Users</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                            @foreach($roles as $r)
                            <tr>
                                <td><strong>{{ \Illuminate\Support\Str::headline($r->name) }}</strong></td>
                                <td>{{ $r->permissions_count }}</td>
                                <td>{{ $r->users_count }}</td>
                                <td class="text-end">
                                    @can('user_roles.edit')
                                        <a href="{{ route('roles.edit', $r) }}" class="btn btn-sm btn-outline-primary">Edit Permissions</a>
                                    @endcan
                                    @can('user_roles.delete')
                                        <form action="{{ route('roles.destroy', $r) }}" method="POST" class="d-inline" onsubmit="return confirm('Delete this role?');">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger" @if($r->users_count > 0) disabled title="Cannot delete a role assigned to users" @endif>Delete</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</div>
@endsection
@extends('layouts.app')

@section('title', 'Accounting | Account Mapping')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header d-flex justify-content-between align-items-center">
                <h2 class="card-title">Accounting</h2>
                <a href="{{ route('accounting.chart') }}" class="btn btn-sm btn-default"><i class="fa fa-arrow-left"></i> Chart of Accounts</a>
            </header>

            @include('accounting._tabs', ['active' => 'mappings'])

            <div class="card-body">
                @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
                @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

                <p class="text-muted">Every automatic posting (invoices, receipts, vehicle costs, expenses…) uses the account chosen here. Re-point a role to change where it posts; an account can only be deleted once no role uses it.</p>

                <form method="POST" action="{{ route('accounting.mappings.update') }}">
                    @csrf @method('PUT')

                    @foreach($groups as $group => $items)
                    <h6 class="text-muted text-uppercase small mt-4 mb-2">{{ $group }}</h6>
                    <div class="table-scroll">
                        <table class="table table-bordered mb-0">
                            <tbody>
                                @foreach($items as $m)
                                <tr>
                                    <td style="width:34%;">
                                        <strong>{{ $m['label'] }}</strong>
                                        @isset($m['help'])<div class="small text-muted">{{ $m['help'] }}</div>@endisset
                                    </td>
                                    <td>
                                        <select name="map[{{ $m['key'] }}]" class="form-control" required @cannot('accounting.edit') disabled @endcannot>
                                            <option value="" disabled @selected(! $m['account_id'])>Select account…</option>
                                            @foreach($m['options'] as $a)
                                                <option value="{{ $a->id }}" @selected($m['account_id'] == $a->id)>{{ $a->code }} — {{ $a->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endforeach

                    @can('accounting.edit')
                        <button type="submit" class="btn btn-primary mt-4"><i class="fa fa-save"></i> Save Mapping</button>
                    @endcan
                </form>

                <div class="alert alert-warning mt-4 mb-0 small">
                    <i class="fa fa-exclamation-triangle"></i> Changing a mapping only affects entries posted <strong>from now on</strong>. To bring past postings in line, run
                    <code>php artisan accounting:restate</code> (dry run) and then <code>php artisan accounting:restate --apply</code>.
                </div>
            </div>
        </section>
    </div>
</div>
@endsection

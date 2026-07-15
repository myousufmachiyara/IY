@extends('layouts.app')

@section('title', 'Accounting | Journal')

@section('content')
<div class="row">
    <div class="col">
        <section class="card">
            <header class="card-header"><h2 class="card-title">Accounting</h2></header>

            @include('accounting._tabs', ['active' => 'journal'])

            <div class="card-body">
                <form method="GET" action="{{ route('accounting.journal') }}" class="row g-2 mb-3">
                    <div class="col-md-3"><input type="date" name="from" class="form-control" value="{{ request('from') }}" title="From"></div>
                    <div class="col-md-3"><input type="date" name="to" class="form-control" value="{{ request('to') }}" title="To"></div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-secondary">Filter</button>
                        @if(request()->anyFilled(['from','to']))
                            <a href="{{ route('accounting.journal') }}" class="btn btn-light">Clear</a>
                        @endif
                    </div>
                </form>

                @foreach ($entries as $entry)
                <div class="card mb-2">
                    <div class="card-body py-2">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div>
                                <strong>{{ $entry->entry_no }}</strong>
                                <span class="text-muted ms-2">{{ $entry->date->format('d-m-Y') }}</span>
                                @if($entry->is_backdated)<span class="badge bg-warning text-dark ms-1">Backdated</span>@endif
                            </div>
                            <span class="text-muted small">{{ $entry->description }}</span>
                        </div>
                        <table class="table table-sm table-borderless mb-0">
                            @foreach ($entry->lines as $line)
                            <tr>
                                <td style="width:40%;">{{ $line->account->name }}</td>
                                <td class="text-end" style="width:30%;">{{ $line->debit > 0 ? '¥'.number_format($line->debit) : '' }}</td>
                                <td class="text-end" style="width:30%;">{{ $line->credit > 0 ? '¥'.number_format($line->credit) : '' }}</td>
                            </tr>
                            @endforeach
                        </table>
                    </div>
                </div>
                @endforeach

                @if($entries->isEmpty())
                    <p class="text-center text-muted py-4">No journal entries found for this range.</p>
                @endif
            </div>
        </section>
    </div>
</div>
@endsection
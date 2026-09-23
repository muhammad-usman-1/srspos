@extends('layouts.admin')

@section('title', __('All Purchases'))
@section('content-header', __('All Purchases'))
@section('content-actions')
    <a href="{{ route('purchases.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> {{ __('New Purchase') }}</a>
@endsection

@section('content')
@php $cur = config('settings.currency_symbol'); @endphp
<div class="container-fluid">
    <div class="row">
        <div class="col-md-3 col-6 mb-3"><div class="card mb-0"><div class="card-body py-3">
            <div class="text-muted small text-uppercase">{{ __('Purchases') }}</div><div class="h4 mb-0">{{ number_format($summary['count']) }}</div>
        </div></div></div>
        <div class="col-md-3 col-6 mb-3"><div class="card mb-0"><div class="card-body py-3">
            <div class="text-muted small text-uppercase">{{ __('Total bought') }}</div><div class="h4 mb-0">{{ $cur }} {{ number_format($summary['total'], 2) }}</div>
        </div></div></div>
        <div class="col-md-3 col-6 mb-3"><div class="card mb-0"><div class="card-body py-3">
            <div class="text-muted small text-uppercase">{{ __('Paid to suppliers') }}</div><div class="h4 mb-0 text-success">{{ $cur }} {{ number_format($summary['paid'], 2) }}</div>
        </div></div></div>
        <div class="col-md-3 col-6 mb-3"><div class="card mb-0"><div class="card-body py-3">
            <div class="text-muted small text-uppercase">{{ __('Owed to suppliers') }}</div><div class="h4 mb-0 {{ $summary['due'] > 0 ? 'text-danger' : '' }}">{{ $cur }} {{ number_format($summary['due'], 2) }}</div>
        </div></div></div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="form-row align-items-end mb-3">
                <div class="col-md-2 mb-2">
                    <label class="small mb-1">{{ __('Status') }}</label>
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="">{{ __('All') }}</option>
                        @foreach(['pending' => __('Pending'), 'completed' => __('Received'), 'cancelled' => __('Cancelled')] as $k => $v)
                            <option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-3 mb-2">
                    <label class="small mb-1">{{ __('Supplier') }}</label>
                    <select name="supplier_id" class="form-control" onchange="this.form.submit()">
                        <option value="">{{ __('All Suppliers') }}</option>
                        @foreach($suppliers as $supplier)
                            <option value="{{ $supplier->id }}" @selected((string) request('supplier_id') === (string) $supplier->id)>{{ $supplier->first_name }} {{ $supplier->last_name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="small mb-1">{{ __('From') }}</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control" onchange="this.form.submit()">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="small mb-1">{{ __('To') }}</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control" onchange="this.form.submit()">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="small mb-1">{{ __('Search') }}</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('# / invoice / notes') }}">
                </div>
                <div class="col-md-1 mb-2">
                    <a href="{{ route('purchases.index') }}" class="btn btn-default btn-block" title="{{ __('Reset') }}"><i class="fas fa-redo"></i></a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                    <tr>
                        <th>#</th>
                        <th>{{ __('Date') }}</th>
                        <th>{{ __('Supplier') }}</th>
                        <th>{{ __('Invoice #') }}</th>
                        <th class="text-center">{{ __('Items') }}</th>
                        <th class="text-right">{{ __('Total') }}</th>
                        <th class="text-right">{{ __('Paid') }}</th>
                        <th class="text-right">{{ __('Due') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th>{{ __('By') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($purchases as $purchase)
                        @php $paid = $purchase->paidAmount(); $due = $purchase->dueAmount(); @endphp
                        <tr>
                            <td><a href="{{ route('purchases.show', $purchase) }}" class="font-weight-bold">#{{ $purchase->id }}</a></td>
                            <td>{{ $purchase->purchase_date->format('d M Y') }}</td>
                            <td>{{ $purchase->supplier?->first_name }} {{ $purchase->supplier?->last_name }}</td>
                            <td>{{ $purchase->reference_no ?: '—' }}</td>
                            <td class="text-center"><span class="badge badge-info">{{ $purchase->items_count }}</span></td>
                            <td class="text-right font-weight-bold">{{ number_format($purchase->total_amount, 2) }}</td>
                            <td class="text-right text-success">{{ number_format($paid, 2) }}</td>
                            <td class="text-right {{ $due > 0 ? 'text-danger font-weight-bold' : 'text-muted' }}">{{ number_format($due, 2) }}</td>
                            <td>@include('purchases.partials.status', ['status' => $purchase->status])</td>
                            <td><small>{{ $purchase->user?->getFullname() }}</small></td>
                            <td class="text-nowrap">
                                <a href="{{ route('purchases.show', $purchase) }}" class="btn btn-sm btn-info" title="{{ __('View') }}"><i class="fas fa-eye"></i></a>
                                <a href="{{ route('purchases.receipt', $purchase) }}" class="btn btn-sm btn-success" target="_blank" title="{{ __('Print') }}"><i class="fas fa-print"></i></a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="11" class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-2x d-block mb-2"></i>{{ __('No purchases found') }}
                        </td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $purchases->links() }}
        </div>
    </div>
</div>
@endsection

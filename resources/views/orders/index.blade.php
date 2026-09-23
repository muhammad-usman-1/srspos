@extends('layouts.admin')

@section('title', __('order.Orders_List'))
@section('content-header', __('order.Orders_List'))
@section('content-actions')
    <a href="{{ route('cart.index') }}" class="btn btn-primary"><i class="fas fa-cash-register"></i> {{ __('Open POS') }}</a>
@endsection

@section('css')
<style>
    .ol-kpi .card-body { padding: 12px 14px; }
    .ol-kpi .lbl { font-size: 11px; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; }
    .ol-kpi .val { font-size: 1.25rem; font-weight: 700; }
    .orders-table td, .orders-table th { vertical-align: middle; white-space: nowrap; }
    .orders-table .sub { font-size: 11px; color: #6c757d; }
</style>
@endsection

@section('content')
@php $cur = config('settings.currency_symbol'); @endphp
<div class="container-fluid">
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="row">
        <div class="col-lg col-md-4 col-6 mb-3"><div class="card ol-kpi mb-0"><div class="card-body">
            <div class="lbl">{{ __('Orders') }}</div><div class="val">{{ number_format($summary['count']) }}</div>
        </div></div></div>
        <div class="col-lg col-md-4 col-6 mb-3"><div class="card ol-kpi mb-0"><div class="card-body">
            <div class="lbl">{{ __('Sales total') }}</div><div class="val">{{ $cur }} {{ number_format($summary['total'], 2) }}</div>
        </div></div></div>
        <div class="col-lg col-md-4 col-6 mb-3"><div class="card ol-kpi mb-0"><div class="card-body">
            <div class="lbl">{{ __('Collected') }}</div><div class="val text-success">{{ $cur }} {{ number_format($summary['received'], 2) }}</div>
        </div></div></div>
        <div class="col-lg col-md-4 col-6 mb-3"><div class="card ol-kpi mb-0"><div class="card-body">
            <div class="lbl">{{ __('Still due') }}</div><div class="val {{ $summary['due'] > 0 ? 'text-danger' : '' }}">{{ $cur }} {{ number_format($summary['due'], 2) }}</div>
        </div></div></div>
        <div class="col-lg col-md-4 col-6 mb-3"><div class="card ol-kpi mb-0"><div class="card-body">
            <div class="lbl">{{ __('Discounts / Tax') }}</div><div class="val" style="font-size:1rem">{{ number_format($summary['discount'], 2) }} / {{ number_format($summary['tax'], 2) }}</div>
        </div></div></div>
        @if($summary['profit'] !== null)
        <div class="col-lg col-md-4 col-6 mb-3"><div class="card ol-kpi mb-0"><div class="card-body">
            <div class="lbl">{{ __('Gross profit') }}</div><div class="val {{ $summary['profit'] < 0 ? 'text-danger' : 'text-primary' }}">{{ $cur }} {{ number_format($summary['profit'], 2) }}</div>
        </div></div></div>
        @endif
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="form-row align-items-end mb-2">
                <div class="col-md-2 mb-2">
                    <label class="small mb-1">{{ __('From') }}</label>
                    <input type="date" name="start_date" class="form-control" value="{{ request('start_date') }}" onchange="this.form.submit()">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="small mb-1">{{ __('To') }}</label>
                    <input type="date" name="end_date" class="form-control" value="{{ request('end_date') }}" onchange="this.form.submit()">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="small mb-1">{{ __('Cashier') }}</label>
                    <select name="cashier" class="form-control" onchange="this.form.submit()">
                        <option value="">{{ __('All') }}</option>
                        @foreach($cashiers as $c)<option value="{{ $c->id }}" @selected((string) request('cashier') === (string) $c->id)>{{ $c->getFullname() }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="small mb-1">{{ __('Payment') }}</label>
                    <select name="method" class="form-control" onchange="this.form.submit()">
                        <option value="">{{ __('All') }}</option>
                        @foreach($methods as $k => $v)<option value="{{ $k }}" @selected(request('method') === $k)>{{ $v }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-1 mb-2">
                    <label class="small mb-1">{{ __('Status') }}</label>
                    <select name="status" class="form-control" onchange="this.form.submit()">
                        <option value="">{{ __('All') }}</option>
                        <option value="paid" @selected(request('status') === 'paid')>{{ __('Paid') }}</option>
                        <option value="partial" @selected(request('status') === 'partial')>{{ __('Partial') }}</option>
                        <option value="unpaid" @selected(request('status') === 'unpaid')>{{ __('Unpaid') }}</option>
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="small mb-1">{{ __('Search') }}</label>
                    <input type="text" name="search" class="form-control" value="{{ request('search') }}" placeholder="{{ __('Bill # / customer') }}">
                </div>
                <div class="col-md-1 mb-2">
                    <a href="{{ route('orders.index') }}" class="btn btn-default btn-block" title="{{ __('Reset') }}"><i class="fas fa-redo"></i></a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover orders-table">
                    <thead>
                    <tr>
                        <th>{{ __('Bill #') }}</th>
                        <th>{{ __('Date & time') }}</th>
                        <th>{{ __('Cashier') }}</th>
                        <th>{{ __('Customer name') }}</th>
                        <th class="text-center">{{ __('Items') }}</th>
                        <th class="text-right">{{ __('Total') }}</th>
                        <th>{{ __('Payment') }}</th>
                        <th class="text-right">{{ __('Cash received') }}</th>
                        <th class="text-right">{{ __('Change given') }}</th>
                        <th class="text-right">{{ __('Due') }}</th>
                        <th>{{ __('Status') }}</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($orders as $order)
                        @php
                            $total = $order->total();
                            $received = $order->receivedAmount();
                            $due = max($total - $received, 0);
                            $tendered = $order->payments->sum(fn($p) => (float) ($p->tendered ?? $p->amount));
                            $change = $order->payments->sum(fn($p) => max((float) ($p->tendered ?? $p->amount) - (float) $p->amount, 0));
                        @endphp
                        <tr>
                            <td>
                                <a href="{{ route('orders.show', $order) }}" class="font-weight-bold">#{{ $order->id }}</a>
                                @if($order->offline_ref)<div class="sub" title="{{ __('Rung up offline') }}"><i class="fas fa-wifi text-muted"></i> {{ $order->offline_ref }}</div>@endif
                            </td>
                            <td>{{ $order->created_at->format('d M Y') }}<div class="sub">{{ $order->created_at->format('h:i A') }}</div></td>
                            <td>{{ $order->user?->getFullname() ?? '—' }}</td>
                            <td>{{ $order->getCustomerName() }}</td>
                            <td class="text-center">{{ $order->items->sum('quantity') }}</td>
                            <td class="text-right font-weight-bold">
                                {{ number_format($total, 2) }}
                                @if($order->discount > 0)<div class="sub">{{ __('disc.') }} {{ number_format($order->discount, 2) }}</div>@endif
                            </td>
                            <td>{{ $order->payments->map(fn($p) => $p->methodLabel())->unique()->implode(', ') ?: '—' }}</td>
                            <td class="text-right">{{ number_format($tendered, 2) }}</td>
                            <td class="text-right">{{ $change > 0 ? number_format($change, 2) : '—' }}</td>
                            <td class="text-right {{ $due > 0 ? 'text-danger font-weight-bold' : 'text-muted' }}">{{ $due > 0 ? number_format($due, 2) : '—' }}</td>
                            <td>
                                @if($received <= 0)<span class="badge badge-danger">{{ __('order.Not_Paid') }}</span>
                                @elseif($due > 0)<span class="badge badge-warning">{{ __('order.Partial') }}</span>
                                @else<span class="badge badge-success">{{ __('order.Paid') }}</span>@endif
                            </td>
                            <td class="text-right">
                                <a href="{{ route('orders.show', $order) }}" class="btn btn-sm btn-secondary" title="{{ __('Details') }}"><i class="fas fa-eye"></i></a>
                                <a href="{{ route('orders.receipt', ['order' => $order, 'print' => 1]) }}" class="btn btn-sm btn-info" target="_blank" title="{{ __('Print bill') }}"><i class="fas fa-print"></i></a>
                                @if($due > 0)
                                    <button class="btn btn-sm btn-primary btnPartialPayment" data-toggle="modal" data-target="#partialPaymentModal"
                                            data-order-id="{{ $order->id }}" data-remaining-amount="{{ round($due, 2) }}" title="{{ __('Take payment') }}">
                                        <i class="fas fa-hand-holding-usd"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="text-center text-muted py-4">{{ __('No orders found.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $orders->links() }}
        </div>
    </div>
</div>

@include('orders.partials.payment-modal')
@endsection

@section('js')
<script type="module">
    jQuery(function ($) {
        var cur = @json($cur);
        $(document).on('click', '.btnPartialPayment', function () {
            var remaining = parseFloat($(this).data('remaining-amount'));
            $('#modalOrderId').val($(this).data('order-id'));
            $('#partialAmount').val(remaining.toFixed(2)).attr('max', remaining);
            $('#remainingAmount').text(cur + ' ' + remaining.toFixed(2));
        });
    });
</script>
@endsection

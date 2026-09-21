@extends('layouts.admin')

@section('title', __('dashboard.title'))
@section('content-header', __('dashboard.title'))
@section('content-actions')
    <a href="{{ route('cart.index') }}" class="btn btn-primary"><i class="fas fa-cash-register"></i> {{ __('Open POS') }}</a>
    <a href="{{ route('stock.index') }}" class="btn btn-outline-secondary"><i class="fas fa-boxes"></i> {{ __('Stock') }}</a>
    <a href="{{ route('purchases.create') }}" class="btn btn-outline-secondary"><i class="fas fa-truck-loading"></i> {{ __('New Purchase') }}</a>
@endsection

@section('css')
<style>
    .kpi { border: 0; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
    .kpi .card-body { display: flex; align-items: center; gap: 14px; padding: 16px; }
    .kpi .ico { width: 46px; height: 46px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 20px; color: #fff; flex: none; }
    .kpi .lbl { font-size: 12px; color: #6c757d; text-transform: uppercase; letter-spacing: .04em; }
    .kpi .val { font-size: 1.5rem; font-weight: 700; line-height: 1.2; }
    .kpi .sub { font-size: 12px; color: #6c757d; }
    .panel { border: 0; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
    .panel .card-header { background: transparent; border-bottom: 1px solid #eef0f3; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
    .panel .card-header::after { display: none; }
    .bars { display: flex; align-items: flex-end; gap: 10px; height: 190px; padding-top: 10px; }
    .bars .bar { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: flex-end; height: 100%; min-width: 0; }
    .bars .fill { width: 100%; max-width: 46px; background: #4f8df7; border-radius: 6px 6px 0 0; min-height: 3px; }
    .bars .bar.today .fill { background: #28a745; }
    .bars .amt { font-size: 11px; color: #495057; margin-bottom: 4px; white-space: nowrap; }
    .bars .day { font-size: 12px; color: #6c757d; margin-top: 6px; }
    .stockrow { display: flex; justify-content: space-between; align-items: center; padding: 9px 0; border-bottom: 1px solid #f1f3f5; }
    .stockrow:last-child { border-bottom: 0; }
</style>
@endsection

@section('content')
@php $cur = config('settings.currency_symbol'); $max = max($days->max('total'), 1); @endphp
<div class="container-fluid">
    <div class="row">
        <div class="col-lg-3 col-sm-6 mb-3">
            <div class="card kpi"><div class="card-body">
                <div class="ico" style="background:#28a745"><i class="fas fa-coins"></i></div>
                <div><div class="lbl">{{ __('Today\'s sales') }}</div><div class="val">{{ $cur }} {{ number_format($income_today, 2) }}</div><div class="sub">{{ $orders_today }} {{ __('orders today') }}</div></div>
            </div></div>
        </div>
        <div class="col-lg-3 col-sm-6 mb-3">
            <div class="card kpi"><div class="card-body">
                <div class="ico" style="background:#4f8df7"><i class="fas fa-calendar-alt"></i></div>
                <div><div class="lbl">{{ __('This month') }}</div><div class="val">{{ $cur }} {{ number_format($income_month, 2) }}</div><div class="sub">{{ __('since') }} {{ today()->startOfMonth()->format('d M') }}</div></div>
            </div></div>
        </div>
        <div class="col-lg-3 col-sm-6 mb-3">
            <div class="card kpi"><div class="card-body">
                <div class="ico" style="background:#6f42c1"><i class="fas fa-receipt"></i></div>
                <div><div class="lbl">{{ __('Total income') }}</div><div class="val">{{ $cur }} {{ number_format($income, 2) }}</div><div class="sub">{{ $orders_count }} {{ __('orders') }} · {{ $customers_count }} {{ __('customers') }}</div></div>
            </div></div>
        </div>
        <div class="col-lg-3 col-sm-6 mb-3">
            <a href="{{ route('stock.index', ['low' => 1]) }}" class="text-dark">
            <div class="card kpi"><div class="card-body">
                <div class="ico" style="background:{{ $low_stock_count ? '#e8590c' : '#20c997' }}"><i class="fas fa-exclamation-triangle"></i></div>
                <div><div class="lbl">{{ __('Low stock') }}</div><div class="val">{{ $low_stock_count }}</div><div class="sub">{{ __('products at or below') }} {{ $threshold }}</div></div>
            </div></div>
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7 mb-3">
            <div class="card panel h-100">
                <div class="card-header">{{ __('Sales – last 7 days') }}</div>
                <div class="card-body">
                    <div class="bars">
                        @foreach($days as $d)
                            <div class="bar {{ $loop->last ? 'today' : '' }}" title="{{ $d['date'] }}: {{ $cur }} {{ number_format($d['total'], 2) }} ({{ $d['orders'] }} orders)">
                                <div class="amt">{{ $d['total'] > 0 ? number_format($d['total'], 0) : '' }}</div>
                                <div class="fill" style="height: {{ round($d['total'] / $max * 100) }}%"></div>
                                <div class="day">{{ $d['label'] }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
        <div class="col-lg-5 mb-3">
            <div class="card panel h-100">
                <div class="card-header d-flex justify-content-between"><span>{{ __('Low stock products') }}</span><a href="{{ route('stock.index', ['low' => 1]) }}">{{ __('View all') }}</a></div>
                <div class="card-body py-2">
                    @forelse($low_stock_products as $p)
                        <div class="stockrow">
                            <span>{{ $p->name }}<br><small class="text-muted">{{ $p->barcode }}</small></span>
                            <span class="badge badge-{{ $p->quantity <= 0 ? 'danger' : 'warning' }}" style="font-size:13px">{{ $p->quantity <= 0 ? __('Out of stock') : $p->quantity . ' ' . __('left') }}</span>
                        </div>
                    @empty
                        <div class="text-center text-muted py-4"><i class="fas fa-check-circle text-success"></i> {{ __('All products are well stocked.') }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-12 mb-3">
            <div class="card panel">
                <div class="card-header d-flex justify-content-between"><span>{{ __('Recent orders') }}</span><a href="{{ route('orders.index') }}">{{ __('View all') }}</a></div>
                <div class="card-body table-responsive p-0">
                    <table class="table mb-0">
                        <thead><tr><th>#</th><th>{{ __('Customer name') }}</th><th>{{ __('Time') }}</th><th class="text-right">{{ __('Total') }}</th><th>{{ __('Payment') }}</th><th></th></tr></thead>
                        <tbody>
                        @forelse($recent_orders as $o)
                            @php $paid = $o->receivedAmount(); $tot = $o->total(); @endphp
                            <tr>
                                <td>{{ $o->id }}</td>
                                <td>{{ $o->getCustomerName() }}</td>
                                <td>{{ $o->created_at->format('d M, h:i A') }}</td>
                                <td class="text-right">{{ $cur }} {{ number_format($tot, 2) }}</td>
                                <td>{{ $o->payments->pluck('method')->unique()->map(fn($m) => \App\Models\Payment::METHODS[$m] ?? $m)->implode(', ') ?: '-' }}</td>
                                <td>
                                    @if($paid <= 0)<span class="badge badge-danger">{{ __('Not paid') }}</span>
                                    @elseif($paid < $tot)<span class="badge badge-warning">{{ __('Partial') }}</span>
                                    @else<span class="badge badge-success">{{ __('Paid') }}</span>@endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">{{ __('No orders yet. Open the POS to make your first sale.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

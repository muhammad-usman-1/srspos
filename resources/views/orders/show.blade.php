@extends('layouts.admin')

@section('title', __('Sale') . ' #' . $order->id)
@section('content-header', __('Sale') . ' #' . $order->id)
@section('content-actions')
    <a href="{{ route('orders.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> {{ __('Back') }}</a>
    <a href="{{ route('orders.receipt', ['order' => $order, 'print' => 1]) }}" class="btn btn-info" target="_blank"><i class="fas fa-print"></i> {{ __('Print bill') }}</a>
@endsection

@section('content')
@php
    $cur = config('settings.currency_symbol');
    $subtotal = $order->subtotal();
    $total = $order->total();
    $received = $order->receivedAmount();
    $due = max($total - $received, 0);
    $tendered = $order->payments->sum(fn($p) => (float) ($p->tendered ?? $p->amount));
    $change = $order->payments->sum(fn($p) => max((float) ($p->tendered ?? $p->amount) - (float) $p->amount, 0));
    $showCost = $profit !== null;
    $cogs = $order->items->sum(fn($i) => (float) $i->cost_price * $i->quantity);
    $missingCost = $order->items->contains(fn($i) => $i->cost_price === null);
@endphp
<div class="container-fluid">
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

    <div class="row">
        <div class="col-lg-4">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-receipt"></i> {{ __('Transaction') }}</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">{{ __('Bill #') }}</dt><dd class="col-7">#{{ $order->id }}</dd>
                        @if($order->offline_ref)
                            <dt class="col-5">{{ __('Offline ref') }}</dt><dd class="col-7">{{ $order->offline_ref }} <small class="text-muted">({{ __('rung up offline, synced later') }})</small></dd>
                        @endif
                        <dt class="col-5">{{ __('Date & time') }}</dt><dd class="col-7">{{ $order->created_at->format('d M Y, h:i:s A') }}</dd>
                        <dt class="col-5">{{ __('Cashier') }}</dt><dd class="col-7">{{ $order->user?->getFullname() ?? '—' }}</dd>
                        <dt class="col-5">{{ __('Customer name') }}</dt><dd class="col-7">{{ $order->getCustomerName() }}</dd>
                        <dt class="col-5">{{ __('Status') }}</dt>
                        <dd class="col-7">
                            @if($received <= 0)<span class="badge badge-danger">{{ __('Not paid') }}</span>
                            @elseif($due > 0)<span class="badge badge-warning">{{ __('Partly paid') }}</span>
                            @else<span class="badge badge-success">{{ __('Paid') }}</span>@endif
                        </dd>
                    </dl>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-calculator"></i> {{ __('Amounts') }}</h3></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between"><span>{{ __('Subtotal') }}</span><span>{{ number_format($subtotal, 2) }}</span></div>
                    @if($order->discount > 0)
                        <div class="d-flex justify-content-between text-warning"><span>{{ __('Discount') }}</span><span>&minus; {{ number_format($order->discount, 2) }}</span></div>
                    @endif
                    @if($order->tax_amount > 0)
                        <div class="d-flex justify-content-between"><span>{{ __('Tax') }} ({{ rtrim(rtrim(number_format($order->tax_rate, 2), '0'), '.') }}%)</span><span>{{ number_format($order->tax_amount, 2) }}</span></div>
                    @endif
                    <div class="d-flex justify-content-between font-weight-bold border-top pt-1 mt-1" style="font-size:1.1rem"><span>{{ __('Total') }}</span><span>{{ $cur }} {{ number_format($total, 2) }}</span></div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between"><span>{{ __('Cash / amount received') }}</span><span>{{ number_format($tendered, 2) }}</span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('Change given back') }}</span><span>{{ number_format($change, 2) }}</span></div>
                    <div class="d-flex justify-content-between text-success"><span>{{ __('Kept (paid)') }}</span><span>{{ number_format($received, 2) }}</span></div>
                    <div class="d-flex justify-content-between {{ $due > 0 ? 'text-danger font-weight-bold' : 'text-muted' }}"><span>{{ __('Still due') }}</span><span>{{ number_format($due, 2) }}</span></div>
                    @if($due > 0)
                        <button class="btn btn-primary btn-sm btn-block mt-3" data-toggle="modal" data-target="#partialPaymentModal"><i class="fas fa-hand-holding-usd"></i> {{ __('Take payment') }}</button>
                    @endif
                </div>
            </div>

            @if($showCost)
            <div class="card card-outline card-success">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-chart-line"></i> {{ __('Profit on this sale') }}</h3></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between"><span>{{ __('Sales (after discount, before tax)') }}</span><span>{{ number_format($subtotal - $order->discount, 2) }}</span></div>
                    <div class="d-flex justify-content-between"><span>{{ __('Cost of goods') }}</span><span>&minus; {{ number_format($cogs, 2) }}</span></div>
                    <div class="d-flex justify-content-between font-weight-bold border-top pt-1 mt-1 {{ $profit < 0 ? 'text-danger' : 'text-success' }}"><span>{{ __('Gross profit') }}</span><span>{{ $cur }} {{ number_format($profit, 2) }}</span></div>
                    @if($missingCost)<small class="text-warning d-block mt-2"><i class="fas fa-exclamation-triangle"></i> {{ __('Some items had no cost price when sold, so their cost is counted as 0.') }}</small>@endif
                    <small class="text-muted d-block mt-2">{{ __('Admin only — the customer\'s bill shows sale prices only.') }}</small>
                </div>
            </div>
            @endif
        </div>

        <div class="col-lg-8">
            <div class="card card-success card-outline">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-shopping-basket"></i> {{ __('Items sold') }}</h3></div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr>
                            <th>{{ __('Item') }}</th>
                            <th class="text-center">{{ __('Qty') }}</th>
                            <th class="text-right">{{ __('Sale price') }}</th>
                            <th class="text-right">{{ __('Line total') }}</th>
                            @if($showCost)
                                <th class="text-right">{{ __('Unit cost') }}</th>
                                <th class="text-right">{{ __('Profit') }}</th>
                            @endif
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($order->items as $item)
                            @php $lineProfit = $item->price - (float) $item->cost_price * $item->quantity; @endphp
                            <tr>
                                <td><strong>{{ $item->product?->name ?? __('Deleted product') }}</strong>@if($item->product)<br><small class="text-muted">{{ $item->product->barcode }}</small>@endif</td>
                                <td class="text-center">{{ $item->quantity }}</td>
                                <td class="text-right">{{ number_format($item->unitPrice(), 2) }}</td>
                                <td class="text-right font-weight-bold">{{ number_format($item->price, 2) }}</td>
                                @if($showCost)
                                    <td class="text-right">{{ $item->cost_price !== null ? number_format($item->cost_price, 2) : '—' }}</td>
                                    <td class="text-right {{ $lineProfit < 0 ? 'text-danger' : 'text-success' }}">{{ $item->cost_price !== null ? number_format($lineProfit, 2) : '—' }}</td>
                                @endif
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-money-bill-wave"></i> {{ __('Payments') }}</h3></div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('When') }}</th><th>{{ __('Method') }}</th><th class="text-right">{{ __('Handed over') }}</th><th class="text-right">{{ __('Change') }}</th><th class="text-right">{{ __('Kept') }}</th><th>{{ __('Received by') }}</th></tr></thead>
                        <tbody>
                        @forelse($order->payments as $p)
                            @php $t = (float) ($p->tendered ?? $p->amount); @endphp
                            <tr>
                                <td>{{ $p->created_at->format('d M Y, h:i A') }}</td>
                                <td>{{ $p->methodLabel() }}</td>
                                <td class="text-right">{{ number_format($t, 2) }}</td>
                                <td class="text-right">{{ number_format(max($t - (float) $p->amount, 0), 2) }}</td>
                                <td class="text-right font-weight-bold">{{ number_format($p->amount, 2) }}</td>
                                <td>{{ $p->user?->getFullname() ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-3">{{ __('No payment recorded.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            @if($movements->isNotEmpty())
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-boxes"></i> {{ __('Stock taken out by this sale') }}</h3></div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Item') }}</th><th class="text-right">{{ __('Change') }}</th><th class="text-right">{{ __('Stock after') }}</th></tr></thead>
                        <tbody>
                        @foreach($movements as $mv)
                            <tr><td>{{ $mv->product?->name }}</td><td class="text-right text-danger">{{ $mv->quantity }}</td><td class="text-right">{{ $mv->balance_after }}</td></tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endif

            @if($activity->isNotEmpty())
            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-history"></i> {{ __('Activity') }}</h3></div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @foreach($activity as $a)
                            <li class="list-group-item py-2">
                                <span class="badge badge-{{ $a->badge() }}">{{ $a->label() }}</span>
                                {{ $a->description }}
                                <small class="text-muted d-block">{{ $a->created_at->format('d M Y, h:i A') }} · {{ $a->user?->getFullname() ?? __('System') }}</small>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
            @endif
        </div>
    </div>
</div>

@include('orders.partials.payment-modal', ['orderId' => $order->id, 'due' => $due])
@endsection

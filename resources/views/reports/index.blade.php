@extends('layouts.admin')

@section('title', __('Reports'))
@section('content-header', __('Sales & Profit Reports'))
@section('content-actions')
    <button type="button" class="btn btn-outline-secondary" onclick="window.print()"><i class="fas fa-print"></i> {{ __('Print') }}</button>
@endsection

@section('css')
<style>
    .rp-card { border: 0; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
    .rp-card .lbl { font-size: 12px; text-transform: uppercase; letter-spacing: .04em; color: #6c757d; }
    .rp-card .big { font-size: 1.45rem; font-weight: 700; line-height: 1.2; }
    .rp-card .row-kv { display: flex; justify-content: space-between; font-size: 13px; padding: 1px 0; }
    .rp-table td, .rp-table th { white-space: nowrap; vertical-align: middle; }
    .rp-table tfoot th { border-top: 2px solid #343a40; }
    .rp-bars { display: flex; align-items: flex-end; gap: 4px; height: 160px; padding-top: 8px; }
    .rp-bars .b { flex: 1; min-width: 0; display: flex; flex-direction: column; justify-content: flex-end; height: 100%; }
    .rp-bars .f { background: #4f8df7; border-radius: 4px 4px 0 0; min-height: 2px; }
    .rp-bars .f.p { background: #28a745; }
    .rp-bars .f.neg { background: #dc3545; }
    .rp-bars .x { font-size: 10px; color: #6c757d; text-align: center; margin-top: 4px; overflow: hidden; white-space: nowrap; }
    @media print { .no-print, .main-sidebar, .main-header, .main-footer { display: none !important; } .content-wrapper { margin: 0 !important; } }
</style>
@endsection

@section('content')
@php
    $cur = config('settings.currency_symbol');
    $f = fn($v) => number_format((float) $v, 2);
@endphp
<div class="container-fluid">

    {{-- Today / this month / this year --}}
    <div class="row">
        @foreach($cards as $key => $c)
            <div class="col-md-4 mb-3">
                <div class="card rp-card h-100"><div class="card-body">
                    <div class="lbl">{{ $c['label'] }}</div>
                    @if($tracksStock)
                        <div class="big {{ $c['profit'] < 0 ? 'text-danger' : 'text-success' }}">{{ $cur }} {{ $f($c['profit']) }}</div>
                        <div class="text-muted small mb-2">{{ __('gross profit') }} · {{ number_format($c['margin'], 1) }}% {{ __('margin') }}</div>
                    @else
                        <div class="big">{{ $cur }} {{ $f($c['net']) }}</div>
                        <div class="text-muted small mb-2">{{ __('net sales') }}</div>
                    @endif
                    <div class="row-kv"><span>{{ __('Orders') }}</span><span>{{ $c['orders'] }}</span></div>
                    <div class="row-kv"><span>{{ __('Net sales') }}</span><span>{{ $f($c['net']) }}</span></div>
                    @if($tracksStock)<div class="row-kv"><span>{{ __('Cost of goods') }}</span><span>{{ $f($c['cost']) }}</span></div>@endif
                    <div class="row-kv"><span>{{ __('Tax collected') }}</span><span>{{ $f($c['tax']) }}</span></div>
                </div></div>
            </div>
        @endforeach
    </div>

    {{-- Period selector --}}
    <div class="card no-print">
        <div class="card-body py-2">
            <form method="GET" class="form-inline flex-wrap">
                <div class="btn-group mr-3 mb-2" role="group">
                    @foreach(['daily' => __('Daily'), 'monthly' => __('Monthly'), 'yearly' => __('Yearly')] as $k => $v)
                        <a href="{{ route('reports.index', ['period' => $k, 'month' => $month->format('Y-m'), 'year' => $year]) }}"
                           class="btn btn-sm {{ $period === $k ? 'btn-primary' : 'btn-outline-primary' }}">{{ $v }}</a>
                    @endforeach
                </div>
                <input type="hidden" name="period" value="{{ $period }}">
                @if($period === 'daily')
                    <label class="mr-2 mb-2 small">{{ __('Month') }}</label>
                    <input type="month" name="month" value="{{ $month->format('Y-m') }}" max="{{ now()->format('Y-m') }}" class="form-control form-control-sm mr-2 mb-2" onchange="this.form.submit()">
                @elseif($period === 'monthly')
                    <label class="mr-2 mb-2 small">{{ __('Year') }}</label>
                    <select name="year" class="form-control form-control-sm mr-2 mb-2" onchange="this.form.submit()">
                        @foreach($years as $y)<option value="{{ $y }}" @selected($y === $year)>{{ $y }}</option>@endforeach
                    </select>
                @endif
                <span class="text-muted small mb-2">{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</span>
            </form>
        </div>
    </div>

    {{-- Chart --}}
    @php $maxV = max($table->max(fn($r) => max($tracksStock ? abs($r['profit']) : 0, $r['net'])), 1); @endphp
    <div class="card rp-card">
        <div class="card-header bg-transparent d-flex justify-content-between">
            <strong>{{ $tracksStock ? __('Net sales vs gross profit') : __('Net sales') }}</strong>
            <span class="small"><span class="badge" style="background:#4f8df7">&nbsp;</span> {{ __('Net sales') }}
                @if($tracksStock)&nbsp; <span class="badge" style="background:#28a745">&nbsp;</span> {{ __('Profit') }}@endif</span>
        </div>
        <div class="card-body">
            <div class="rp-bars">
                @foreach($table as $r)
                    <div class="b" title="{{ $r['label'] }}: {{ __('sales') }} {{ $f($r['net']) }}{{ $tracksStock ? ', ' . __('profit') . ' ' . $f($r['profit']) : '' }}">
                        <div class="d-flex align-items-end" style="height:100%; gap:1px">
                            <div class="f" style="flex:1; height: {{ round($r['net'] / $maxV * 100) }}%"></div>
                            @if($tracksStock)<div class="f p {{ $r['profit'] < 0 ? 'neg' : '' }}" style="flex:1; height: {{ round(abs($r['profit']) / $maxV * 100) }}%"></div>@endif
                        </div>
                        <div class="x">{{ $period === 'daily' ? \Illuminate\Support\Str::after($r['label'], ', ') : ($period === 'monthly' ? \Illuminate\Support\Str::limit($r['label'], 3, '') : $r['label']) }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- Breakdown table --}}
    <div class="card rp-card">
        <div class="card-header bg-transparent"><strong>{{ ['daily' => __('Day by day'), 'monthly' => __('Month by month'), 'yearly' => __('Year by year')][$period] }}</strong></div>
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover table-sm mb-0 rp-table">
                <thead class="thead-light">
                <tr>
                    <th>{{ __('Period') }}</th>
                    <th class="text-right">{{ __('Orders') }}</th>
                    <th class="text-right">{{ __('Items') }}</th>
                    <th class="text-right">{{ __('Gross sales') }}</th>
                    <th class="text-right">{{ __('Discounts') }}</th>
                    <th class="text-right">{{ __('Net sales') }}</th>
                    <th class="text-right">{{ __('Tax') }}</th>
                    <th class="text-right">{{ __('Collected') }}</th>
                    <th class="text-right">{{ __('Due') }}</th>
                    @if($tracksStock)
                        <th class="text-right">{{ __('Cost of goods') }}</th>
                        <th class="text-right">{{ __('Gross profit') }}</th>
                        <th class="text-right">{{ __('Margin') }}</th>
                    @endif
                </tr>
                </thead>
                <tbody>
                @foreach($table as $r)
                    <tr class="{{ $r['orders'] === 0 ? 'text-muted' : '' }}">
                        <td>
                            @if($period === 'daily' && $r['orders'] > 0)
                                <a href="{{ route('orders.index', ['start_date' => $r['key'], 'end_date' => $r['key']]) }}">{{ $r['label'] }}</a>
                            @elseif($period === 'monthly')
                                <a href="{{ route('reports.index', ['period' => 'daily', 'month' => $r['key']]) }}">{{ $r['label'] }}</a>
                            @elseif($period === 'yearly')
                                <a href="{{ route('reports.index', ['period' => 'monthly', 'year' => $r['key']]) }}">{{ $r['label'] }}</a>
                            @else
                                {{ $r['label'] }}
                            @endif
                        </td>
                        <td class="text-right">{{ $r['orders'] }}</td>
                        <td class="text-right">{{ $r['qty'] }}</td>
                        <td class="text-right">{{ $f($r['gross']) }}</td>
                        <td class="text-right">{{ $r['discount'] > 0 ? '−' . $f($r['discount']) : '—' }}</td>
                        <td class="text-right font-weight-bold">{{ $f($r['net']) }}</td>
                        <td class="text-right">{{ $f($r['tax']) }}</td>
                        <td class="text-right text-success">{{ $f($r['paid']) }}</td>
                        <td class="text-right {{ $r['due'] > 0 ? 'text-danger' : '' }}">{{ $r['due'] > 0 ? $f($r['due']) : '—' }}</td>
                        @if($tracksStock)
                            <td class="text-right">{{ $f($r['cost']) }}</td>
                            <td class="text-right font-weight-bold {{ $r['profit'] < 0 ? 'text-danger' : 'text-success' }}">{{ $f($r['profit']) }}</td>
                            <td class="text-right">{{ $r['net'] > 0 ? number_format($r['margin'], 1) . '%' : '—' }}</td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
                <tfoot>
                <tr>
                    <th>{{ __('Total') }}</th>
                    <th class="text-right">{{ $totals['orders'] }}</th>
                    <th class="text-right">{{ $totals['qty'] }}</th>
                    <th class="text-right">{{ $f($totals['gross']) }}</th>
                    <th class="text-right">{{ $totals['discount'] > 0 ? '−' . $f($totals['discount']) : '—' }}</th>
                    <th class="text-right">{{ $cur }} {{ $f($totals['net']) }}</th>
                    <th class="text-right">{{ $f($totals['tax']) }}</th>
                    <th class="text-right text-success">{{ $f($totals['paid']) }}</th>
                    <th class="text-right {{ $totals['due'] > 0 ? 'text-danger' : '' }}">{{ $f($totals['due']) }}</th>
                    @if($tracksStock)
                        <th class="text-right">{{ $f($totals['cost']) }}</th>
                        <th class="text-right {{ $totals['profit'] < 0 ? 'text-danger' : 'text-success' }}">{{ $cur }} {{ $f($totals['profit']) }}</th>
                        <th class="text-right">{{ $totals['net'] > 0 ? number_format($totals['margin'], 1) . '%' : '—' }}</th>
                    @endif
                </tr>
                </tfoot>
            </table>
        </div>
        @if($tracksStock && $totals['nocost'] > 0)
            <div class="card-footer small text-warning">
                <i class="fas fa-exclamation-triangle"></i>
                {{ __(':n sold line(s) in this range had no cost price at the time of sale; their cost is counted as 0, so profit is overstated for them. Set a cost price on every product.', ['n' => $totals['nocost']]) }}
            </div>
        @endif
    </div>

    <div class="row">
        <div class="col-lg-{{ $purchases ? 8 : 12 }}">
            <div class="card rp-card">
                <div class="card-header bg-transparent"><strong>{{ $tracksStock ? __('Most profitable products') : __('Best selling products') }}</strong> <small class="text-muted">({{ $from->format('d M Y') }} – {{ $to->format('d M Y') }})</small></div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm mb-0 rp-table">
                        <thead class="thead-light">
                        <tr>
                            <th>{{ __('Item') }}</th>
                            <th class="text-right">{{ __('Qty sold') }}</th>
                            <th class="text-right">{{ __('Sales') }}</th>
                            @if($tracksStock)<th class="text-right">{{ __('Cost') }}</th><th class="text-right">{{ __('Profit') }}</th>@endif
                        </tr>
                        </thead>
                        <tbody>
                        @forelse($topProducts as $p)
                            <tr>
                                <td>{{ $p->name ?? __('Deleted product') }} <small class="text-muted">{{ $p->barcode }}</small></td>
                                <td class="text-right">{{ (int) $p->qty }}</td>
                                <td class="text-right">{{ $f($p->sales) }}</td>
                                @if($tracksStock)
                                    <td class="text-right">{{ $f($p->cost) }}</td>
                                    <td class="text-right font-weight-bold {{ $p->sales - $p->cost < 0 ? 'text-danger' : 'text-success' }}">{{ $f($p->sales - $p->cost) }}</td>
                                @endif
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">{{ __('No sales in this range.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer small text-muted">{{ __('Product figures are line totals before bill-level discounts.') }}</div>
            </div>
        </div>
        @if($purchases)
        <div class="col-lg-4">
            <div class="card rp-card">
                <div class="card-header bg-transparent"><strong>{{ __('Stock purchased') }}</strong> <small class="text-muted">({{ __('received') }})</small></div>
                <div class="card-body">
                    <div class="row-kv"><span>{{ __('Purchases') }}</span><span>{{ $purchases['count'] }}</span></div>
                    <div class="row-kv"><span>{{ __('Total bought') }}</span><span class="font-weight-bold">{{ $cur }} {{ $f($purchases['total']) }}</span></div>
                    <div class="row-kv text-success"><span>{{ __('Paid to suppliers') }}</span><span>{{ $f($purchases['paid']) }}</span></div>
                    <div class="row-kv {{ $purchases['due'] > 0 ? 'text-danger' : '' }}"><span>{{ __('Still owed') }}</span><span>{{ $f($purchases['due']) }}</span></div>
                    <a href="{{ route('purchases.index', ['date_from' => $from->toDateString(), 'date_to' => $to->toDateString(), 'status' => 'completed']) }}" class="btn btn-sm btn-outline-primary btn-block mt-3 no-print">{{ __('View purchases') }}</a>
                </div>
            </div>
            <div class="card rp-card">
                <div class="card-body small text-muted">
                    <strong class="text-dark">{{ __('How profit is worked out') }}</strong><br>
                    {{ __('Net sales = sales − bill discounts (tax is excluded — it is not income).') }}<br>
                    {{ __('Cost of goods = each item\'s cost price at the moment it was sold.') }}<br>
                    {{ __('Gross profit = net sales − cost of goods. Shop expenses (rent, salaries…) are not deducted.') }}
                </div>
            </div>
        </div>
        @endif
    </div>

    @unless($tracksStock)
        <div class="alert alert-light border small">
            <i class="fas fa-info-circle"></i> {{ __('This store does not track stock, so there are no cost prices and profit cannot be calculated. Sales figures are shown instead.') }}
        </div>
    @endunless
</div>
@endsection

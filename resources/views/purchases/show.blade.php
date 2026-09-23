@extends('layouts.admin')

@section('title', __('Purchase') . ' #' . $purchase->id)
@section('content-header', __('Purchase') . ' #' . $purchase->id)
@section('content-actions')
    <a href="{{ route('purchases.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> {{ __('Back') }}</a>
    <a href="{{ route('purchases.receipt', $purchase) }}" class="btn btn-success" target="_blank"><i class="fas fa-print"></i> {{ __('Print') }}</a>
@endsection

@section('content')
@php
    $cur = config('settings.currency_symbol');
    $paid = $purchase->paidAmount();
    $due = $purchase->dueAmount();
@endphp
<div class="container-fluid">
    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <div class="row">
        <div class="col-lg-4">
            <div class="card card-primary card-outline">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-info-circle"></i> {{ __('Purchase Information') }}</h3></div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-5">{{ __('Status') }}</dt>
                        <dd class="col-7">@include('purchases.partials.status', ['status' => $purchase->status])</dd>
                        <dt class="col-5">{{ __('Purchase date') }}</dt>
                        <dd class="col-7">{{ $purchase->purchase_date->format('d M Y') }}</dd>
                        <dt class="col-5">{{ __('Supplier invoice #') }}</dt>
                        <dd class="col-7">{{ $purchase->reference_no ?: '—' }}</dd>
                        <dt class="col-5">{{ __('Supplier') }}</dt>
                        <dd class="col-7">
                            {{ $purchase->supplier?->first_name }} {{ $purchase->supplier?->last_name }}
                            @if($purchase->supplier?->phone)<br><small><a href="tel:{{ $purchase->supplier->phone }}">{{ $purchase->supplier->phone }}</a></small>@endif
                        </dd>
                        <dt class="col-5">{{ __('Entered by') }}</dt>
                        <dd class="col-7">{{ $purchase->user?->getFullname() ?? '—' }}</dd>
                        <dt class="col-5">{{ __('Entered at') }}</dt>
                        <dd class="col-7"><small>{{ $purchase->created_at->format('d M Y, h:i A') }}</small></dd>
                    </dl>
                    @if($purchase->notes)
                        <hr><strong>{{ __('Notes') }}:</strong><br><small>{{ $purchase->notes }}</small>
                    @endif
                </div>
                <div class="card-footer">
                    @if($purchase->status === 'pending')
                        <form method="POST" action="{{ route('purchases.status', $purchase) }}" class="d-inline" onsubmit="return confirm('{{ __('Mark as received? The items will be added to stock.') }}')">
                            @csrf <input type="hidden" name="status" value="completed">
                            <button class="btn btn-success btn-sm"><i class="fas fa-check"></i> {{ __('Mark received (add stock)') }}</button>
                        </form>
                    @endif
                    @if($purchase->status !== 'cancelled')
                        <form method="POST" action="{{ route('purchases.status', $purchase) }}" class="d-inline" onsubmit="return confirm('{{ $purchase->status === 'completed' ? __('Cancel this purchase? Its items will be taken back out of stock.') : __('Cancel this purchase?') }}')">
                            @csrf <input type="hidden" name="status" value="cancelled">
                            <button class="btn btn-outline-danger btn-sm"><i class="fas fa-ban"></i> {{ __('Cancel purchase') }}</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('purchases.status', $purchase) }}" class="d-inline">
                            @csrf <input type="hidden" name="status" value="pending">
                            <button class="btn btn-outline-secondary btn-sm"><i class="fas fa-undo"></i> {{ __('Reopen as pending') }}</button>
                        </form>
                    @endif
                </div>
            </div>

            <div class="card card-outline {{ $due > 0 ? 'card-danger' : 'card-success' }}">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-money-bill-wave"></i> {{ __('Supplier payment') }}</h3></div>
                <div class="card-body">
                    <div class="d-flex justify-content-between"><span>{{ __('Purchase total') }}</span><strong>{{ $cur }} {{ number_format($purchase->total_amount, 2) }}</strong></div>
                    <div class="d-flex justify-content-between text-success"><span>{{ __('Paid') }}</span><strong>{{ $cur }} {{ number_format($paid, 2) }}</strong></div>
                    <div class="d-flex justify-content-between {{ $due > 0 ? 'text-danger' : 'text-muted' }} border-top pt-1 mt-1"><span>{{ __('Still owed') }}</span><strong>{{ $cur }} {{ number_format($due, 2) }}</strong></div>

                    @if($due > 0)
                        <form method="POST" action="{{ route('purchases.payments.store', $purchase) }}" class="mt-3">
                            @csrf
                            <div class="form-row">
                                <div class="col-6 mb-2"><input type="number" step="0.01" min="0.01" max="{{ $due }}" name="amount" value="{{ old('amount', $due) }}" class="form-control form-control-sm" required></div>
                                <div class="col-6 mb-2">
                                    <select name="method" class="form-control form-control-sm">
                                        @foreach($methods as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
                                    </select>
                                </div>
                                <div class="col-12 mb-2"><input type="text" name="note" maxlength="255" class="form-control form-control-sm" placeholder="{{ __('Note (optional), e.g. cheque no.') }}"></div>
                            </div>
                            <button class="btn btn-primary btn-sm btn-block"><i class="fas fa-plus"></i> {{ __('Record payment to supplier') }}</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card card-success card-outline">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-shopping-basket"></i> {{ __('Items purchased') }}</h3></div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                        <tr>
                            <th>{{ __('Item') }}</th>
                            <th class="text-center">{{ __('Qty') }}</th>
                            <th class="text-right">{{ __('Unit cost') }}</th>
                            <th class="text-right">{{ __('Line total') }}</th>
                            <th class="text-right">{{ __('Current sale price') }}</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($purchase->items as $item)
                            <tr>
                                <td>
                                    <strong>{{ $item->product?->name ?? __('Deleted product') }}</strong>
                                    @if($item->product)<br><small class="text-muted">{{ $item->product->barcode }}</small>@endif
                                </td>
                                <td class="text-center"><span class="badge badge-info">{{ $item->quantity }}</span></td>
                                <td class="text-right">{{ number_format($item->purchase_price, 2) }}</td>
                                <td class="text-right font-weight-bold">{{ number_format($item->subtotal, 2) }}</td>
                                <td class="text-right">
                                    @if($item->product)
                                        {{ number_format($item->product->price, 2) }}
                                        @php $m = $item->product->price > 0 ? ($item->product->price - $item->purchase_price) / $item->product->price * 100 : 0; @endphp
                                        <br><small class="{{ $m < 0 ? 'text-danger' : 'text-success' }}">{{ number_format($m, 1) }}% {{ __('margin') }}</small>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                        <tfoot>
                        <tr class="bg-light">
                            <th>{{ __('Total') }}</th>
                            <th class="text-center">{{ $purchase->items->sum('quantity') }}</th>
                            <th></th>
                            <th class="text-right">{{ $cur }} {{ number_format($purchase->total_amount, 2) }}</th>
                            <th></th>
                        </tr>
                        </tfoot>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-receipt"></i> {{ __('Payments to supplier') }}</h3></div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Method') }}</th><th class="text-right">{{ __('Amount') }}</th><th>{{ __('Paid by') }}</th><th>{{ __('Note') }}</th></tr></thead>
                        <tbody>
                        @forelse($purchase->payments as $pay)
                            <tr>
                                <td>{{ $pay->created_at->format('d M Y, h:i A') }}</td>
                                <td>{{ $pay->methodLabel() }}</td>
                                <td class="text-right font-weight-bold">{{ number_format($pay->amount, 2) }}</td>
                                <td>{{ $pay->user?->getFullname() ?? '—' }}</td>
                                <td><small>{{ $pay->note }}</small></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">{{ __('Nothing paid yet.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="card">
                <div class="card-header"><h3 class="card-title"><i class="fas fa-history"></i> {{ __('Stock movements from this purchase') }}</h3></div>
                <div class="card-body p-0 table-responsive">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>{{ __('When') }}</th><th>{{ __('Item') }}</th><th>{{ __('Type') }}</th><th class="text-right">{{ __('Change') }}</th><th class="text-right">{{ __('Stock after') }}</th></tr></thead>
                        <tbody>
                        @forelse($movements as $mv)
                            <tr>
                                <td>{{ $mv->created_at->format('d M Y, h:i A') }}</td>
                                <td>{{ $mv->product?->name }}</td>
                                <td>{{ $mv->typeLabel() }}</td>
                                <td class="text-right {{ $mv->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ $mv->quantity > 0 ? '+' : '' }}{{ $mv->quantity }}</td>
                                <td class="text-right">{{ $mv->balance_after }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-center text-muted py-3">{{ $purchase->status === 'pending' ? __('Not received yet — stock is added when you mark it received.') : __('No stock movements.') }}</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

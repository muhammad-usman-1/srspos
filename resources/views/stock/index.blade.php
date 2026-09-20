@extends('layouts.admin')

@section('title', __('Stock Management'))
@section('content-header', __('Stock Management'))
@section('content-actions')
    <a href="{{ route('stock.movements') }}" class="btn btn-secondary"><i class="fas fa-history"></i> {{ __('Stock History') }}</a>
    <a href="{{ route('products.index') }}" class="btn btn-outline-secondary"><i class="fas fa-th-large"></i> {{ __('Product List') }}</a>
    <a href="{{ route('products.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> {{ __('New Product') }}</a>
    <a href="{{ route('purchases.create') }}" class="btn btn-success"><i class="fas fa-truck-loading"></i> {{ __('New Purchase') }}</a>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-4">
            <div class="small-box bg-info"><div class="inner"><h4>{{ config('settings.currency_symbol') }} {{ number_format($stockValue, 2) }}</h4><p>{{ __('Stock value (at cost)') }}</p></div></div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-success"><div class="inner"><h4>{{ config('settings.currency_symbol') }} {{ number_format($retailValue, 2) }}</h4><p>{{ __('Stock value (at sale price)') }}</p></div></div>
        </div>
        <div class="col-md-4">
            <div class="small-box bg-warning"><div class="inner"><h4>{{ $lowCount }}</h4><p>{{ __('Low stock products (≤ :n)', ['n' => $threshold]) }}</p></div></div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <form method="GET" class="form-inline mb-3">
                <input type="text" name="search" value="{{ request('search') }}" class="form-control mr-2" placeholder="{{ __('Search name or barcode') }}">
                <div class="form-check mr-2">
                    <input type="checkbox" class="form-check-input" name="low" value="1" id="low" {{ request('low') ? 'checked' : '' }}>
                    <label class="form-check-label" for="low">{{ __('Low stock only') }}</label>
                </div>
                <button class="btn btn-primary">{{ __('Filter') }}</button>
            </form>

            <table class="table table-striped">
                <thead>
                <tr>
                    <th>{{ __('Product Name') }}</th>
                    <th>{{ __('Barcode') }}</th>
                    <th class="text-right">{{ __('Cost') }}</th>
                    <th class="text-right">{{ __('Price') }}</th>
                    <th class="text-right">{{ __('In Stock') }}</th>
                    <th>{{ __('Actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse($products as $product)
                    <tr>
                        <td>{{ $product->name }}</td>
                        <td>{{ $product->barcode }}</td>
                        <td class="text-right">{{ $product->purchase_price !== null ? number_format($product->purchase_price, 2) : '-' }}</td>
                        <td class="text-right">{{ number_format($product->price, 2) }}</td>
                        <td class="text-right">
                            <span class="badge badge-{{ $product->quantity <= 0 ? 'danger' : ($product->quantity <= $threshold ? 'warning' : 'success') }}">{{ $product->quantity }}</span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-success btn-adjust" data-mode="add" data-id="{{ $product->id }}" data-name="{{ $product->name }}" data-qty="{{ $product->quantity }}" data-cost="{{ $product->purchase_price }}"><i class="fas fa-plus"></i> {{ __('Add') }}</button>
                            <button class="btn btn-sm btn-danger btn-adjust" data-mode="remove" data-id="{{ $product->id }}" data-name="{{ $product->name }}" data-qty="{{ $product->quantity }}"><i class="fas fa-minus"></i> {{ __('Remove') }}</button>
                            <button class="btn btn-sm btn-secondary btn-adjust" data-mode="set" data-id="{{ $product->id }}" data-name="{{ $product->name }}" data-qty="{{ $product->quantity }}"><i class="fas fa-equals"></i> {{ __('Set') }}</button>
                            <a class="btn btn-sm btn-outline-info" title="{{ __('Stock History') }}" href="{{ route('stock.movements', ['product_id' => $product->id]) }}"><i class="fas fa-history"></i></a>
                            <a class="btn btn-sm btn-outline-primary" title="{{ __('Edit product') }}" href="{{ route('products.edit', $product) }}"><i class="fas fa-edit"></i></a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">{{ __('No products found.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $products->render() }}
        </div>
    </div>
@endsection

@section('model')
    <div class="modal fade" id="adjustModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <form method="POST" id="adjustForm" class="modal-content">
                @csrf
                <input type="hidden" name="mode" id="adjustMode">
                <div class="modal-header">
                    <h5 class="modal-title" id="adjustTitle"></h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted mb-2">{{ __('Current stock') }}: <strong id="adjustCurrent"></strong></p>
                    <div class="form-group">
                        <label id="adjustQtyLabel" for="adjustQty">{{ __('Quantity') }}</label>
                        <input type="number" min="0" step="1" name="quantity" id="adjustQty" class="form-control" required>
                    </div>
                    <div class="form-group" id="costGroup">
                        <label for="adjustCost">{{ __('New purchase price per unit (optional)') }}</label>
                        <input type="number" min="0" step="0.01" name="purchase_price" id="adjustCost" class="form-control">
                    </div>
                    <div class="form-group">
                        <label for="adjustNote">{{ __('Note / reason') }}</label>
                        <input type="text" name="note" id="adjustNote" class="form-control" maxlength="255">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Save') }}</button>
                </div>
            </form>
        </div>
    </div>
@endsection

@section('js')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            var titles = {add: '{{ __('Add stock') }}', remove: '{{ __('Remove stock') }}', set: '{{ __('Set stock count') }}'};
            var labels = {add: '{{ __('Quantity to add') }}', remove: '{{ __('Quantity to remove') }}', set: '{{ __('New stock count') }}'};
            var base = '{{ url('admin/stock') }}';
            document.querySelectorAll('.btn-adjust').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var d = btn.dataset;
                    document.getElementById('adjustForm').action = base + '/' + d.id + '/adjust';
                    document.getElementById('adjustMode').value = d.mode;
                    document.getElementById('adjustTitle').textContent = titles[d.mode] + ' — ' + d.name;
                    document.getElementById('adjustQtyLabel').textContent = labels[d.mode];
                    document.getElementById('adjustCurrent').textContent = d.qty;
                    document.getElementById('adjustQty').value = d.mode === 'set' ? d.qty : '';
                    document.getElementById('adjustCost').value = d.cost || '';
                    document.getElementById('costGroup').style.display = d.mode === 'add' ? '' : 'none';
                    document.getElementById('adjustNote').value = '';
                    $('#adjustModal').modal('show');
                });
            });
        });
    </script>
@endsection

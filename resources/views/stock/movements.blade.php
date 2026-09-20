@extends('layouts.admin')

@section('title', __('Stock History'))
@section('content-header', __('Stock History'))
@section('content-actions')
    <a href="{{ route('stock.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> {{ __('Back to stock') }}</a>
@endsection

@section('content')
    <div class="card">
        <div class="card-body">
            <form method="GET" class="form-inline mb-3">
                <select name="product_id" class="form-control mr-2">
                    <option value="">{{ __('All products') }}</option>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" {{ request('product_id') == $p->id ? 'selected' : '' }}>{{ $p->name }}</option>
                    @endforeach
                </select>
                <select name="type" class="form-control mr-2">
                    <option value="">{{ __('All types') }}</option>
                    @foreach(\App\Models\StockMovement::TYPES as $key => $label)
                        <option value="{{ $key }}" {{ request('type') === $key ? 'selected' : '' }}>{{ __($label) }}</option>
                    @endforeach
                </select>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control mr-2">
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control mr-2">
                <button class="btn btn-primary">{{ __('Filter') }}</button>
            </form>

            <table class="table table-striped">
                <thead>
                <tr>
                    <th>{{ __('Date') }}</th>
                    <th>{{ __('Product Name') }}</th>
                    <th>{{ __('Type') }}</th>
                    <th class="text-right">{{ __('Change') }}</th>
                    <th class="text-right">{{ __('Balance') }}</th>
                    <th>{{ __('Reference') }}</th>
                    <th>{{ __('Note') }}</th>
                    <th>{{ __('User') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse($movements as $m)
                    <tr>
                        <td>{{ $m->created_at->format('d M Y H:i') }}</td>
                        <td>{{ $m->product->name ?? '-' }}</td>
                        <td>{{ $m->typeLabel() }}</td>
                        <td class="text-right"><span class="text-{{ $m->quantity >= 0 ? 'success' : 'danger' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</span></td>
                        <td class="text-right">{{ $m->balance_after }}</td>
                        <td>{{ $m->reference }}</td>
                        <td>{{ $m->note }}</td>
                        <td>{{ $m->user->name ?? '-' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted">{{ __('No stock movements yet.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $movements->render() }}
        </div>
    </div>
@endsection

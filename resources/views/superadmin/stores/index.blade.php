@extends('layouts.admin')

@section('title', __('Stores'))
@section('content-header', __('Stores'))
@section('content-actions')
    <a href="{{ route('superadmin.stores.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> {{ __('New Store') }}</a>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-4"><div class="small-box bg-info"><div class="inner"><h3>{{ $stores->count() }}</h3><p>{{ __('Stores') }}</p></div></div></div>
        <div class="col-md-4"><div class="small-box bg-success"><div class="inner"><h3>{{ $stores->where('is_active', true)->count() }}</h3><p>{{ __('Active') }}</p></div></div></div>
        <div class="col-md-4"><div class="small-box bg-warning"><div class="inner"><h3>{{ $stores->where('is_active', false)->count() }}</h3><p>{{ __('Suspended') }}</p></div></div></div>
    </div>

    <div class="card">
        <div class="card-body table-responsive p-0">
            <table class="table table-striped mb-0">
                <thead>
                <tr>
                    <th>{{ __('Store') }}</th>
                    <th>{{ __('Owner login') }}</th>
                    <th class="text-right">{{ __('Products') }}</th>
                    <th class="text-right">{{ __('Orders') }}</th>
                    <th class="text-right">{{ __('Total sales') }}</th>
                    <th>{{ __('Status') }}</th>
                    <th>{{ __('Created') }}</th>
                    <th>{{ __('Actions') }}</th>
                </tr>
                </thead>
                <tbody>
                @forelse($stores as $store)
                    <tr>
                        <td><strong>{{ $store->name }}</strong></td>
                        <td>{{ $store->owner?->getFullname() }}<br><small class="text-muted">{{ $store->owner?->email }}</small></td>
                        <td class="text-right">{{ $store->products_count }}</td>
                        <td class="text-right">{{ $store->orders_count }}</td>
                        <td class="text-right">{{ config('settings.currency_symbol', 'PKR') }} {{ number_format($store->total_sales, 2) }}</td>
                        <td>
                            <span class="badge badge-{{ $store->is_active ? 'success' : 'danger' }}">{{ $store->is_active ? __('Active') : __('Suspended') }}</span>
                        </td>
                        <td>{{ $store->created_at->format('d M Y') }}</td>
                        <td class="text-nowrap">
                            <a href="{{ route('superadmin.stores.edit', $store) }}" class="btn btn-sm btn-primary" title="{{ __('Edit') }}"><i class="fas fa-edit"></i></a>
                            <form method="POST" action="{{ route('superadmin.stores.toggle', $store) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-{{ $store->is_active ? 'warning' : 'success' }}" title="{{ $store->is_active ? __('Suspend') : __('Activate') }}">
                                    <i class="fas fa-{{ $store->is_active ? 'pause' : 'play' }}"></i>
                                </button>
                            </form>
                            <form method="POST" action="{{ route('superadmin.stores.destroy', $store) }}" class="d-inline"
                                  onsubmit="return confirm('{{ __('Delete this store and ALL of its products, orders, customers and stock? This cannot be undone.') }}');">
                                @csrf
                                @method('DELETE')
                                <button class="btn btn-sm btn-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted p-4">{{ __('No stores yet. Create the first one.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection

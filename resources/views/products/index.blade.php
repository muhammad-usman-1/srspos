@extends('layouts.admin')

@section('title', __('product.Product_List'))
@section('content-header', __('product.Product_List'))
@section('content-actions')
<a href="{{route('products.create')}}" class="btn btn-primary">{{ __('product.Create_Product') }}</a>
@endsection
@section('css')
<link rel="stylesheet" href="{{ asset('plugins/sweetalert2/sweetalert2.min.css') }}">
@endsection
@section('content')
<div class="card product-list">
    <div class="card-body">
        <table class="table">
            <thead>
                <tr>
                    <th>{{ __('product.ID') }}</th>
                    <th>{{ __('product.Name') }}</th>
                    <th>{{ __('product.Image') }}</th>
                    <th>{{ __('product.Barcode') }}</th>
                    @if(store_tracks_stock())
                    <th class="text-right">{{ __('Cost') }}</th>
                    <th class="text-right">{{ __('Sale price') }}</th>
                    <th class="text-right">{{ __('Margin') }}</th>
                    <th class="text-right">{{ __('product.Quantity') }}</th>
                    @else
                    <th class="text-right">{{ __('Sale price') }}</th>
                    @endif
                    <th>{{ __('product.Status') }}</th>
                    <th>{{ __('product.Created_At') }}</th>
                    <th>{{ __('product.Updated_At') }}</th>
                    <th>{{ __('product.Actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($products as $product)
                <tr>
                    <td>{{$product->id}}</td>
                    <td>{{$product->name}}</td>
                    <td><img class="product-img" src="{{ Storage::url($product->image) }}" alt=""></td>
                    <td>{{$product->barcode}}</td>
                    @if(store_tracks_stock())
                    @php $cost = $product->purchase_price; $margin = $cost !== null && $product->price > 0 ? ($product->price - $cost) / $product->price * 100 : null; @endphp
                    <td class="text-right">{{ $cost !== null ? number_format($cost, 2) : '—' }}</td>
                    <td class="text-right">{{ number_format($product->price, 2) }}</td>
                    <td class="text-right">
                        @if($margin === null)<span class="text-muted">—</span>
                        @else<span class="{{ $margin < 0 ? 'text-danger' : 'text-success' }} font-weight-bold">{{ number_format($margin, 1) }}%</span>@endif
                    </td>
                    <td class="text-right">{{$product->quantity}}</td>
                    @else
                    <td class="text-right">{{ number_format($product->price, 2) }}</td>
                    @endif
                    <td>
                        <span class="right badge badge-{{ $product->status ? 'success' : 'danger' }}">{{$product->status ? __('common.Active') : __('common.Inactive') }}</span>
                    </td>
                    <td>{{$product->created_at}}</td>
                    <td>{{$product->updated_at}}</td>
                    <td>
                        <a href="{{ route('products.edit', $product) }}" class="btn btn-primary"><i class="fas fa-edit"></i></a>
                        <button class="btn btn-danger btn-delete" data-url="{{route('products.destroy', $product)}}"><i class="fas fa-trash"></i></button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        {{ $products->render() }}
    </div>
</div>
@endsection

@section('js')
<script src="{{ asset('plugins/sweetalert2/sweetalert2.min.js') }}"></script>
<script type="module">
    $(document).ready(function() {
        $(document).on('click', '.btn-delete', function() {
            var $this = $(this);
            const swalWithBootstrapButtons = Swal.mixin({
                customClass: {
                    confirmButton: 'btn btn-success',
                    cancelButton: 'btn btn-danger'
                },
                buttonsStyling: false
            })

            swalWithBootstrapButtons.fire({
                title: '{{ __('product.sure ') }}', // Wrap in quotes
                text: '{{ __('product.really_delete ') }}', // Wrap in quotes
                icon: 'warning', // Fix the icon string
                showCancelButton: true,
                confirmButtonText: '{{ __('product.yes_delete ') }}', // Wrap in quotes
                cancelButtonText: '{{ __('product.No ') }}', // Wrap in quotes
                reverseButtons: true
            }).then((result) => {
                if (result.value) {
                    $.post($this.data('url'), {
                        _method: 'DELETE',
                        _token: '{{ csrf_token() }}' // Wrap in quotes
                    }, function(res) {
                        $this.closest('tr').fadeOut(500, function() {
                            $(this).remove();
                        });
                    }).fail(function(xhr) {
                        Swal.fire('{{ __('Cannot delete') }}', (xhr.responseJSON && xhr.responseJSON.message) || '{{ __('Something went wrong.') }}', 'error');
                    });
                }
            });
        });
    });
</script>
@endsection
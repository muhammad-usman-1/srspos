@extends('layouts.admin')

@section('title', __('product.Edit_Product'))
@section('content-header', __('product.Edit_Product'))

@section('content')

<div class="card">
    <div class="card-body">

        <form action="{{ route('products.update', $product) }}" method="POST" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="form-group">
                <label for="name">{{ __('product.Name') }}</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" id="name"
                    placeholder="{{ __('product.Name') }}" value="{{ old('name', $product->name) }}">
                @error('name')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
                @enderror
            </div>


            <div class="form-group">
                <label for="description">{{ __('product.Description') }}</label>
                <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                    id="description"
                    placeholder="{{ __('product.Description') }}">{{ old('description', $product->description) }}</textarea>
                @error('description')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
                @enderror
            </div>

            <div class="form-group">
                <label for="image">{{ __('product.Image') }}</label>
                <div class="custom-file">
                    <input type="file" class="custom-file-input" name="image" id="image">
                    <label class="custom-file-label" for="image">{{ __('product.Choose_file') }}</label>
                </div>
                @error('image')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
                @enderror
            </div>

            <div class="form-group">
                <label for="barcode">{{ __('product.Barcode') }}</label>
                <input type="text" name="barcode" class="form-control @error('barcode') is-invalid @enderror"
                    id="barcode" placeholder="{{ __('product.Barcode') }}" value="{{ old('barcode', $product->barcode) }}">
                @error('barcode')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
                @enderror
            </div>

            @if(store_tracks_stock())
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="purchase_price">{{ __('Cost price') }} <small class="text-muted">({{ __('what it costs you — never shown to customers') }})</small></label>
                    <input type="number" step="0.01" min="0" name="purchase_price" class="form-control @error('purchase_price') is-invalid @enderror" id="purchase_price"
                        placeholder="0.00" value="{{ old('purchase_price', $product->purchase_price) }}">
                    @error('purchase_price')
                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                    @enderror
                </div>
                <div class="form-group col-md-6">
                    <label for="price">{{ __('Sale price') }} <small class="text-muted">({{ __('printed on the bill') }})</small></label>
                    <input type="number" step="0.01" min="0" name="price" class="form-control @error('price') is-invalid @enderror" id="price"
                        placeholder="0.00" value="{{ old('price', $product->price) }}">
                    @error('price')
                    <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                    @enderror
                </div>
            </div>
            <div class="mb-3 small" id="margin-hint"></div>
            @else
            <div class="form-group">
                <label for="price">{{ __('Sale price') }}</label>
                <input type="number" step="0.01" min="0" name="price" class="form-control @error('price') is-invalid @enderror" id="price"
                    placeholder="0.00" value="{{ old('price', $product->price) }}">
                @error('price')
                <span class="invalid-feedback" role="alert"><strong>{{ $message }}</strong></span>
                @enderror
            </div>
            @endif

            <div class="form-group">
                <label for="mkt_price">{{ __('Market Price (MKT) - optional') }}</label>
                <input type="text" name="mkt_price" class="form-control @error('mkt_price') is-invalid @enderror" id="mkt_price"
                    placeholder="{{ __('Shown on the bill so customers see what they saved') }}" value="{{ old('mkt_price', $product->mkt_price) }}">
                @error('mkt_price')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
                @enderror
            </div>

            @if(store_tracks_stock())
            <div class="form-group">
                <label for="quantity">{{ __('product.Quantity') }}</label>
                <input type="text" name="quantity" class="form-control @error('quantity') is-invalid @enderror"
                    id="quantity" placeholder="{{ __('product.Quantity') }}" value="{{ old('quantity', $product->quantity) }}">
                @error('quantity')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
                @enderror
            </div>
            @endif

            <div class="form-group">
                <label for="status">{{ __('product.Status') }}</label>
                <select name="status" class="form-control @error('status') is-invalid @enderror" id="status">
                    <option value="1" {{ old('status', $product->status) === 1 ? 'selected' : ''}}>{{ __('common.Active') }}</option>
                    <option value="0" {{ old('status', $product->status) === 0 ? 'selected' : ''}}>{{ __('common.Inactive') }}</option>
                </select>
                @error('status')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
                @enderror
            </div>

            <button class="btn btn-primary" type="submit">{{ __('common.Update') }}</button>
        </form>
    </div>
</div>
@endsection

@section('js')
<script src="{{ asset('plugins/bs-custom-file-input/bs-custom-file-input.min.js') }}"></script>
{{-- module script: runs after the app bundle (which provides jQuery) has loaded --}}
<script type="module">
    $(document).ready(function () {
        bsCustomFileInput.init();
    });

    // Live profit per unit / margin while typing cost and sale price
    (function () {
        var cost = document.getElementById('purchase_price'), sale = document.getElementById('price'), out = document.getElementById('margin-hint');
        if (!cost || !sale || !out) return;
        var cur = @json(config('settings.currency_symbol'));
        function update() {
            var c = parseFloat(cost.value), s = parseFloat(sale.value);
            if (isNaN(c) || isNaN(s)) { out.innerHTML = ''; return; }
            var p = s - c, m = s > 0 ? (p / s * 100) : 0;
            out.className = 'mb-3 small font-weight-bold ' + (p < 0 ? 'text-danger' : 'text-success');
            out.textContent = (p < 0 ? 'Loss' : 'Profit') + ' per unit: ' + cur + ' ' + p.toFixed(2) + ' (' + m.toFixed(1) + '% margin)'
                + (p < 0 ? ' — sale price is below cost' : '');
        }
        cost.addEventListener('input', update); sale.addEventListener('input', update); update();
    })();

    // Auto-capitalize the first letter of each word as the admin types (e.g. "coca cola" -> "Coca Cola")
    document.getElementById('name').addEventListener('input', function (e) {
        var el = e.target;
        var pos = el.selectionStart;
        var capped = el.value.replace(/(^|\s)([a-z])/g, function (m, before, letter) {
            return before + letter.toUpperCase();
        });
        if (capped !== el.value) {
            el.value = capped;
            el.selectionStart = el.selectionEnd = pos;
        }
    });
</script>
@endsection
@extends('layouts.admin')

@section('title', __('settings.Update_Settings'))
@section('content-header', __('settings.Update_Settings'))

@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ route('settings.store') }}" method="post" enctype="multipart/form-data">
            @csrf

            <div class="form-group">
                <label for="logo">{{ __('Logo') }}</label>
                <div class="mb-2">
                    <img src="{{ app_logo_url() }}" alt="Logo" style="max-height:80px;max-width:200px;background:#eee;padding:4px;border-radius:4px">
                </div>
                <div class="custom-file">
                    <input type="file" class="custom-file-input @error('logo') is-invalid @enderror" name="logo" id="logo" accept="image/*">
                    <label class="custom-file-label" for="logo">{{ __('Choose logo (PNG/JPG, max 2MB)') }}</label>
                </div>
                @error('logo')
                <span class="text-danger small" role="alert"><strong>{{ $message }}</strong></span>
                @enderror
                @if(config('settings.logo'))
                    <div class="form-check mt-2">
                        <input type="checkbox" class="form-check-input" name="remove_logo" value="1" id="remove_logo">
                        <label class="form-check-label" for="remove_logo">{{ __('Remove custom logo') }}</label>
                    </div>
                @endif
                <small class="form-text text-muted">{{ __('Shown in the sidebar, login page and printed receipts.') }}</small>
            </div>

            <div class="form-group">
                <label for="app_name">{{ __('settings.app_name') }}</label>
                <input type="text" name="app_name" class="form-control @error('app_name') is-invalid @enderror" id="app_name" placeholder="{{ __('settings.App_name') }}" value="{{ old('app_name', config('settings.app_name')) }}">
                @error('app_name')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
                @enderror
            </div>

            <div class="form-group">
                <label for="app_description">{{ __('settings.app_description') }}</label>
                <textarea name="app_description" class="form-control @error('app_description') is-invalid @enderror" id="app_description" placeholder="{{ __('settings.app_description') }}">{{ old('app_description', config('settings.app_description')) }}</textarea>
                @error('app_description')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
                @enderror
            </div>

            <div class="form-group">
                <label for="currency_symbol">{{ __('settings.Currency_symbol') }}</label>
                <input type="text" name="currency_symbol" class="form-control @error('currency_symbol') is-invalid @enderror" id="currency_symbol" placeholder="{{ __('settings.Currency_symbol') }}" value="{{ old('currency_symbol', config('settings.currency_symbol')) }}">
                @error('currency_symbol')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
                @enderror
            </div>
            <div class="form-group">
                <label for="warning_quantity">{{ __('settings.warning_quantity') }}</label>
                <input type="text" name="warning_quantity" class="form-control @error('warning_quantity') is-invalid @enderror" id="warning_quantity" placeholder="{{ __('settings.warning_quantity') }}" value="{{ old('warning_quantity', config('settings.warning_quantity')) }}">
                @error('warning_quantity')
                <span class="invalid-feedback" role="alert">
                    <strong>{{ $message }}</strong>
                </span>
                @enderror
            </div>
            <hr>
            <h5>{{ __('Bill / Receipt') }}</h5>
            <div class="form-group">
                <label for="store_address">{{ __('Store address') }}</label>
                <textarea name="store_address" id="store_address" rows="3" class="form-control" placeholder="Shop address, city">{{ old('store_address', config('settings.store_address')) }}</textarea>
            </div>
            <div class="form-group">
                <label for="store_phone">{{ __('Phone number(s)') }}</label>
                <input type="text" name="store_phone" id="store_phone" class="form-control" placeholder="0343-1234567 - 0300-1234567" value="{{ old('store_phone', config('settings.store_phone')) }}">
            </div>
            <div class="form-group">
                <label for="receipt_title">{{ __('Bill heading') }}</label>
                <input type="text" name="receipt_title" id="receipt_title" class="form-control" placeholder="Original Sales Invoice" value="{{ old('receipt_title', config('settings.receipt_title')) }}">
            </div>
            <div class="form-group">
                <label for="receipt_policy">{{ __('Return / refund policy (printed in a box)') }}</label>
                <textarea name="receipt_policy" id="receipt_policy" rows="3" class="form-control" placeholder="No refund or return without bill. Return or exchange within 3 days. All taxes are inclusive.">{{ old('receipt_policy', config('settings.receipt_policy')) }}</textarea>
            </div>
            <div class="form-group">
                <label for="receipt_footer">{{ __('Thank-you line') }}</label>
                <input type="text" name="receipt_footer" id="receipt_footer" class="form-control" placeholder="Thanks for your visit" value="{{ old('receipt_footer', config('settings.receipt_footer')) }}">
            </div>
            <div class="form-group">
                <label for="receipt_credit">{{ __('Bottom small line (optional)') }}</label>
                <input type="text" name="receipt_credit" id="receipt_credit" class="form-control" placeholder="Software by ..." value="{{ old('receipt_credit', config('settings.receipt_credit')) }}">
            </div>

            <hr>
            <h5>{{ __('Checkout: Discount & Tax') }}</h5>
            <div class="form-group">
                <input type="hidden" name="enable_discount" value="0">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="enable_discount" name="enable_discount" value="1" {{ old('enable_discount', config('settings.enable_discount')) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="enable_discount">{{ __('Allow discount at checkout') }}</label>
                </div>
            </div>
            <div class="form-group">
                <input type="hidden" name="enable_tax" value="0">
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="enable_tax" name="enable_tax" value="1" {{ old('enable_tax', config('settings.enable_tax')) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="enable_tax">{{ __('Apply tax at checkout') }}</label>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="tax_name">{{ __('Tax name') }}</label>
                    <input type="text" name="tax_name" id="tax_name" class="form-control" placeholder="GST / Sales Tax" value="{{ old('tax_name', config('settings.tax_name', 'Tax')) }}">
                </div>
                <div class="form-group col-md-6">
                    <label for="tax_rate">{{ __('Default tax rate (%)') }}</label>
                    <input type="number" step="0.01" min="0" max="100" name="tax_rate" id="tax_rate" class="form-control" value="{{ old('tax_rate', config('settings.tax_rate', 0)) }}">
                    <small class="form-text text-muted">{{ __('The cashier can still change the rate at checkout.') }}</small>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">{{ __('settings.Change_Setting') }}</button>
        </form>
    </div>
</div>
@endsection

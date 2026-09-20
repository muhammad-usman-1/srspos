@extends('layouts.admin')

@php($editing = $store->exists)
@section('title', $editing ? __('Edit Store') : __('New Store'))
@section('content-header', $editing ? __('Edit Store') : __('New Store'))
@section('content-actions')
    <a href="{{ route('superadmin.stores.index') }}" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> {{ __('Back') }}</a>
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        <form method="POST" action="{{ $editing ? route('superadmin.stores.update', $store) : route('superadmin.stores.store') }}">
            @csrf
            @if($editing) @method('PUT') @endif

            <h5>{{ __('Store') }}</h5>
            <div class="form-group">
                <label for="name">{{ __('Store name') }}</label>
                <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $store->name) }}" required>
                @error('name')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
            </div>
            @if($editing)
                <input type="hidden" name="is_active" value="0">
                <div class="custom-control custom-switch mb-3">
                    <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" {{ old('is_active', $store->is_active) ? 'checked' : '' }}>
                    <label class="custom-control-label" for="is_active">{{ __('Store is active (users can log in)') }}</label>
                </div>
            @endif

            <hr>
            <h5>{{ __('Store login') }}</h5>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="first_name">{{ __('First Name') }}</label>
                    <input type="text" name="first_name" id="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name', $owner->first_name) }}" required>
                    @error('first_name')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
                </div>
                <div class="form-group col-md-6">
                    <label for="last_name">{{ __('Last Name') }}</label>
                    <input type="text" name="last_name" id="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name', $owner->last_name) }}" required>
                    @error('last_name')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
                </div>
            </div>
            <div class="form-group">
                <label for="email">{{ __('Email (login)') }}</label>
                <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $owner->email) }}" required>
                @error('email')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="password">{{ $editing ? __('New password (leave blank to keep)') : __('Password') }}</label>
                    <input type="password" name="password" id="password" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror" {{ $editing ? '' : 'required' }}>
                    @error('password')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
                </div>
                <div class="form-group col-md-6">
                    <label for="password_confirmation">{{ __('Confirm password') }}</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password" class="form-control" {{ $editing ? '' : 'required' }}>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">{{ $editing ? __('Save Changes') : __('Create Store') }}</button>
        </form>
    </div>
</div>
@endsection

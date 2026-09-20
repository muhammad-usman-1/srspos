@extends('layouts.admin')

@section('title', __('My Profile'))
@section('content-header', __('My Profile'))

@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ route('profile.update') }}" method="POST">
            @csrf
            @method('PUT')

            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="first_name">{{ __('First Name') }}</label>
                    <input type="text" name="first_name" id="first_name" class="form-control @error('first_name') is-invalid @enderror" value="{{ old('first_name', $user->first_name) }}" required>
                    @error('first_name')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
                </div>
                <div class="form-group col-md-6">
                    <label for="last_name">{{ __('Last Name') }}</label>
                    <input type="text" name="last_name" id="last_name" class="form-control @error('last_name') is-invalid @enderror" value="{{ old('last_name', $user->last_name) }}" required>
                    @error('last_name')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
                </div>
            </div>

            <div class="form-group">
                <label for="email">{{ __('Email') }}</label>
                <input type="email" name="email" id="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
                @error('email')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
            </div>

            <hr>
            <h5>{{ __('Change Password') }} <small class="text-muted">({{ __('leave blank to keep the current one') }})</small></h5>

            <div class="form-group">
                <label for="current_password">{{ __('Current Password') }}</label>
                <input type="password" name="current_password" id="current_password" autocomplete="current-password" class="form-control @error('current_password') is-invalid @enderror">
                @error('current_password')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
            </div>
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="password">{{ __('New Password') }}</label>
                    <input type="password" name="password" id="password" autocomplete="new-password" class="form-control @error('password') is-invalid @enderror">
                    @error('password')<span class="invalid-feedback"><strong>{{ $message }}</strong></span>@enderror
                </div>
                <div class="form-group col-md-6">
                    <label for="password_confirmation">{{ __('Confirm New Password') }}</label>
                    <input type="password" name="password_confirmation" id="password_confirmation" autocomplete="new-password" class="form-control">
                </div>
            </div>

            <button type="submit" class="btn btn-primary">{{ __('Save Changes') }}</button>
        </form>
    </div>
</div>
@endsection

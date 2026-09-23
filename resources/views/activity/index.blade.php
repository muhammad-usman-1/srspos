@extends('layouts.admin')

@section('title', __('Activity Log'))
@section('content-header', __('Activity Log'))

@section('content')
<div class="container-fluid">
    <div class="card">
        <div class="card-body">
            <form method="GET" class="form-row align-items-end mb-3">
                <div class="col-md-3 mb-2">
                    <label class="small mb-1">{{ __('Type') }}</label>
                    <select name="action" class="form-control" onchange="this.form.submit()">
                        <option value="">{{ __('All') }}</option>
                        @foreach($actions as $k => [$label])
                            <option value="{{ $k }}" @selected(request('action') === $k)>{{ __($label) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="small mb-1">{{ __('User') }}</label>
                    <select name="user" class="form-control" onchange="this.form.submit()">
                        <option value="">{{ __('All') }}</option>
                        @foreach($users as $u)<option value="{{ $u->id }}" @selected((string) request('user') === (string) $u->id)>{{ $u->getFullname() }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2">
                    <label class="small mb-1">{{ __('From') }}</label>
                    <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control" onchange="this.form.submit()">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="small mb-1">{{ __('To') }}</label>
                    <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control" onchange="this.form.submit()">
                </div>
                <div class="col-md-2 mb-2">
                    <label class="small mb-1">{{ __('Search') }}</label>
                    <input type="text" name="search" value="{{ request('search') }}" class="form-control" placeholder="{{ __('e.g. #25, product name') }}">
                </div>
                <div class="col-md-1 mb-2">
                    <a href="{{ route('activity.index') }}" class="btn btn-default btn-block" title="{{ __('Reset') }}"><i class="fas fa-redo"></i></a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                    <tr>
                        <th style="width:170px">{{ __('When') }}</th>
                        <th style="width:150px">{{ __('Type') }}</th>
                        <th>{{ __('What happened') }}</th>
                        <th style="width:150px">{{ __('By') }}</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($logs as $log)
                        <tr>
                            <td class="text-nowrap">{{ $log->created_at->format('d M Y, h:i:s A') }}</td>
                            <td><span class="badge badge-{{ $log->badge() }}">{{ $log->label() }}</span></td>
                            <td>
                                @if($url = $log->subjectUrl())<a href="{{ $url }}">{{ $log->description }}</a>@else{{ $log->description }}@endif
                            </td>
                            <td>{{ $log->user?->getFullname() ?? __('System') }}<br><small class="text-muted">{{ $log->ip_address }}</small></td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-muted py-4">{{ __('No activity recorded yet.') }}</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $logs->links() }}
        </div>
    </div>
</div>
@endsection

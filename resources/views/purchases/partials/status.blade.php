@if($status === 'completed')
    <span class="badge badge-success"><i class="fas fa-check-circle"></i> {{ __('Received') }}</span>
@elseif($status === 'pending')
    <span class="badge badge-warning"><i class="fas fa-clock"></i> {{ __('Pending') }}</span>
@else
    <span class="badge badge-danger"><i class="fas fa-times-circle"></i> {{ __('Cancelled') }}</span>
@endif

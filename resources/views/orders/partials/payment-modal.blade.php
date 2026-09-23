<!-- Take a later payment against an unpaid / partly paid sale -->
<div class="modal fade" id="partialPaymentModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('Take payment') }}</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <form method="POST" action="{{ route('orders.partial-payment') }}">
                @csrf
                <div class="modal-body">
                    <input type="hidden" name="order_id" id="modalOrderId" value="{{ $orderId ?? '' }}">
                    <div class="form-group">
                        <label for="partialAmount">{{ __('Amount received') }}</label>
                        <input type="number" class="form-control" step="0.01" min="0.01" id="partialAmount" name="amount" value="{{ isset($due) ? round($due, 2) : '' }}" @isset($due) max="{{ round($due, 2) }}" @endisset required>
                        <small class="form-text text-muted">{{ __('Still due') }}: <span id="remainingAmount">{{ isset($due) ? config('settings.currency_symbol') . ' ' . number_format($due, 2) : '' }}</span></small>
                    </div>
                    <div class="form-group mb-0">
                        <label for="partialMethod">{{ __('Payment method') }}</label>
                        <select class="form-control" id="partialMethod" name="method">
                            @foreach(\App\Models\Payment::METHODS as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">{{ __('Cancel') }}</button>
                    <button type="submit" class="btn btn-primary">{{ __('Record payment') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>

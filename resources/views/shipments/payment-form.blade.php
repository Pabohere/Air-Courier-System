@if($shipment->balanceCents()>0&&$shipment->status!=='Cancelled' && (!$shipment->origin_branch_id || auth()->user()->isAdmin() || auth()->user()->branch_id === $shipment->origin_branch_id || (auth()->user()->branch_id === $shipment->destination_branch_id && $shipment->arrived_at)))
<div class="card">
<div class="card-head">
<h2>
{{ __('Record a payment') }}
</h2>
</div>
<div class="card-body">
<form method="post" action="{{ route('payments.store',$shipment) }}" data-review-confirm data-review-context="{{ $shipment->tracking_number }} · {{ $shipment->recipient_name }} · {{ __('Outstanding') }}: {{ \App\Services\Money::decimal($shipment->balanceCents()) }} {{ $shipment->currency }}" data-confirm="{{ __('Confirm this payment? Check the amount, currency and payment method.') }}" data-payment-error="{{ old('_payment_form') === 'record' ? ($errors->keys()[0] ?? '') : '' }}">
<input type="hidden" name="_payment_form" value="record">
@if(old('_payment_form') === 'record' && $errors->any())
<p class="notice error" role="alert">{{ $errors->first() }}</p>
@endif
@csrf<input type="hidden" name="submission_key" value="{{ old('submission_key',\Illuminate\Support\Str::uuid()) }}">
<div class="field-grid">
@if($shipment->origin_branch_id && auth()->user()->isAdmin())
<label class="full">{{ __('Receiving payment branch') }}<select name="branch_id" required>@foreach($branches->whereIn('id', [$shipment->origin_branch_id, $shipment->destination_branch_id]) as $payBranch)<option value="{{ $payBranch->id }}" @selected(old('branch_id', $shipment->current_branch_id ?? $shipment->origin_branch_id) == $payBranch->id)>{{ $payBranch->code }}</option>@endforeach</select></label>
@endif
<label class="full">
{{ __('Amount (') }}{{ $shipment->currency }})<input type="number" name="amount" min="0.01" max="{{ \App\Services\Money::decimal($shipment->balanceCents()) }}" step="0.01" value="{{ old('amount',\App\Services\Money::decimal($shipment->balanceCents())) }}" required>
</label>
@include('shipments.payment-details')
<label class="full">
{{ __('Payment date') }}<input name="payment_date" type="date" value="{{ old('payment_date',\App\Services\BranchAccess::todayFor()) }}" max="{{ \App\Services\BranchAccess::todayFor() }}" required>
</label>
<label class="full">
{{ __('Reference number') }}<input name="reference" value="{{ old('reference') }}" maxlength="150">
</label>
</div>
<div class="form-actions">
<button class="button">✓ {{ __('Save payment') }}</button>
</div>
</form>
</div>
</div>
@endif

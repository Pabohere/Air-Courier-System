@extends('layouts.app')
@section('title', __('Receive a Parcel'))
@section('content')
@php($originId = old('origin_branch_id', request('origin_branch_id', $operationBranchId ?? auth()->user()->branch_id ?? $branches->first()->id)))
<div class="page-heading"><div><h1>{{ __('Receive a Parcel') }}</h1><p class="subtle">{{ __('Follow the steps. You can go back without losing information.') }}</p></div></div>
@if($errors->any())<p class="notice">{{ __('After an error, choose newly added photos again. Photos saved in a draft are kept.') }}</p>@endif
<form class="guided-form" data-guided-form data-server-errors="{{ json_encode($errors->keys()) }}" data-step-label="{{ __('Step :current of :total') }}" method="post" enctype="multipart/form-data" action="{{ route('shipments.store') }}">
@csrf
<input type="hidden" name="draft_revision" value="{{ old('draft_revision', $draft?->revision ?? 0) }}">
<input type="hidden" name="draft_id" value="{{ old('draft_id', $draft?->key ?? \Illuminate\Support\Str::uuid()) }}">
<input type="hidden" name="booking_key" value="{{ old('booking_key', \Illuminate\Support\Str::uuid()) }}">
<x-workflow-progress :steps="[__('Customer Information'), __('Recipient Information'), __('Parcel Information'), __('Review and Save')]" />
<section class="card" data-step data-step-title="{{ __('Customer Information') }}"><div class="card-head"><h2 tabindex="-1">1. {{ __('Customer Information') }}</h2></div><div class="card-body">
<p>{{ __('Enter the customer phone number. You can look for an existing customer first.') }}</p>
<input type="hidden" name="customer_id" value="{{ old('customer_id') }}"><div data-customer-picker data-url="{{ route('customers.index') }}" data-empty="{{ __('No matching customer. Enter their information below.') }}" data-error="{{ __('Customer search is unavailable. Enter their information below or try again.') }}" data-selected="{{ __('Existing customer selected. Contact details are kept as recorded.') }}">
<div class="customer-search"><label>{{ __('Find customer by phone or name') }}<input type="search" data-customer-search maxlength="100" autocomplete="off"></label><button class="button secondary" type="button" data-customer-find>⌕ {{ __('Find customer') }}</button></div>
<button type="button" class="button secondary" data-customer-clear @if(!old('customer_id')) hidden @endif>{{ __('Use a different customer') }}</button><p role="status" data-customer-status></p><div class="customer-matches" data-customer-matches></div>
</div>
<p class="help">{{ __('Or enter a new customer below.') }}</p>
<div class="field-grid section-gap">
<label>{{ __('Customer name') }}<input name="customer_name" @readonly(old('customer_id')) value="{{ old('customer_name') }}" maxlength="150" autocomplete="name" required data-review-label="{{ __('Customer name') }}"></label>
<label>{{ __('Customer phone') }}<input type="tel" name="customer_phone" @readonly(old('customer_id')) value="{{ old('customer_phone') }}" maxlength="50" pattern="\+?[0-9 \(\)\-]{7,50}" autocomplete="tel" required data-review-label="{{ __('Customer phone') }}"></label>
<label class="full">{{ __('Customer address') }}<input name="customer_address" @readonly(old('customer_id')) value="{{ old('customer_address') }}" maxlength="2000" data-review-label="{{ __('Customer address') }}"></label>
</div>
</div></section>
<section class="card" data-step data-step-title="{{ __('Recipient Information') }}"><div class="card-head"><h2 tabindex="-1">2. {{ __('Recipient Information') }}</h2></div><div class="card-body field-grid">
<label>{{ __('Origin branch') }}<select name="origin_branch_id" data-origin-branch required data-review-label="{{ __('Origin branch') }}">@foreach($branches as $branch) @if(auth()->user()->isAdmin() || auth()->user()->branch_id === $branch->id)<option value="{{ $branch->id }}" @selected($originId == $branch->id)>{{ $branch->code }} · {{ __($branch->name) }}</option>@endif @endforeach</select></label>
<label>{{ __('Destination branch') }}<select name="destination_branch_id" data-destination-branch required data-review-label="{{ __('Destination branch') }}">@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('destination_branch_id', $branches->firstWhere('id', '!=', $originId)?->id) == $branch->id)>{{ $branch->code }} · {{ __($branch->name) }}</option>@endforeach</select></label>
<label>{{ __('Recipient name') }}<input name="recipient_name" value="{{ old('recipient_name') }}" maxlength="150" required data-review-label="{{ __('Recipient name') }}"></label>
<label>{{ __('Recipient phone') }}<input type="tel" name="recipient_phone" value="{{ old('recipient_phone') }}" maxlength="50" pattern="\+?[0-9 \(\)\-]{7,50}" required data-review-label="{{ __('Recipient phone') }}"></label>
<label class="full">{{ __('Recipient address (optional)') }}<textarea name="recipient_address" maxlength="2000" data-review-label="{{ __('Recipient address (optional)') }}">{{ old('recipient_address') }}</textarea></label>
</div></section>
<section class="card" data-step data-step-title="{{ __('Parcel Information') }}"><div class="card-head"><h2 tabindex="-1">3. {{ __('Parcel Information') }}</h2></div><div class="card-body field-grid">
<label class="full">{{ __('Parcel description') }}<textarea name="description" maxlength="2000" required data-review-label="{{ __('Parcel description') }}">{{ old('description') }}</textarea></label>
<label>{{ __('Quantity / pieces') }}<input type="number" inputmode="numeric" name="quantity" value="{{ old('quantity', 1) }}" min="1" max="10000" required data-review-label="{{ __('Quantity / pieces') }}"></label>
<label>{{ __('Actual weight (kg)') }}<input type="number" inputmode="decimal" name="actual_weight" value="{{ old('actual_weight') }}" min="0.001" max="99999.999" step="0.001" required data-review-label="{{ __('Actual weight (kg)') }}"></label>
<label>{{ __('Storage location') }}<input name="storage_location" value="{{ old('storage_location', 'Receiving area') }}" maxlength="100" required data-review-label="{{ __('Storage location') }}"><span class="help">{{ __('Example: Rack A / Shelf 2.') }}</span></label>
<label>{{ __('Parcel received date') }}<input type="date" name="shipment_date" value="{{ old('shipment_date', \App\Services\BranchAccess::todayFor((int) $originId)) }}" required data-review-label="{{ __('Parcel received date') }}"></label>
@include('entry-draft-photos')
@include('shipments.photo-input', ['remaining' => 5 - count($draft?->photos ?? [])])
<label>{{ __('Currency') }}<select name="currency" required data-review-label="{{ __('Currency') }}">@foreach(['MMK','SGD'] as $code)<option value="{{ $code }}" @selected(old('currency', $branches->firstWhere('id', $originId)?->code === 'SG' ? 'SGD' : 'MMK') === $code)>{{ $code }}</option>@endforeach</select></label>
<label>{{ __('Agreed shipment charge') }}<input type="number" inputmode="decimal" name="price" value="{{ old('price') }}" min="0.01" max="999999999.99" step="0.01" required data-review-label="{{ __('Agreed shipment charge') }}"></label>
<p class="help full">{{ __('The saved currency and charge cannot be changed. Payments are recorded in this same currency.') }}</p>
</div></section>
<section class="card" data-step data-step-title="{{ __('Review and Save') }}"><div class="card-head"><h2 tabindex="-1">4. {{ __('Review and Save') }}</h2></div><div class="card-body">
<p>{{ __('Check the information with the customer before saving.') }}</p>
<dl class="summary-list" data-workflow-review></dl>
<p class="notice">{{ __('Payment is not recorded at this step. Receive money after saving the parcel.') }}</p>
<div data-workflow-submit><button class="button" type="submit">✓ {{ __('Save Parcel') }}</button></div>
</div></section>
<div class="guided-actions" data-workflow-controls hidden><button class="button secondary" type="button" data-step-back>← {{ __('Back') }}</button><button class="button" type="button" data-step-next>{{ __('Continue') }} →</button></div>
<div class="draft-actions"><button type="submit" class="button secondary" formaction="{{ route('entry-drafts.store', ['kind' => 'booking']) }}" formnovalidate>{{ __('Save draft') }}</button><span class="help">{{ __('Missing details? Save a draft and finish later. No charges are recorded until booking is confirmed.') }}</span></div>
</form>
@endsection

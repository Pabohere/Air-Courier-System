@extends('layouts.app')
@section('title', __('New shipment'))
@section('content')
<div class="page-heading"><div><div class="eyebrow">{{ __('YGN ↔ SG BRANCH TRANSFER') }}</div><h1>{{ __('Book a shipment') }}</h1><p class="subtle">{{ __('Weigh the parcel, choose its branch route, and enter the agreed charge.') }}</p></div><a class="button secondary" href="{{ route('branches.index') }}">{{ __('Branch operations') }} →</a></div>
<form method="post" enctype="multipart/form-data" action="{{ route('shipments.store') }}">
@csrf<input type="hidden" name="draft_revision" value="{{ old('draft_revision', $draft?->revision ?? 0) }}"><input type="hidden" name="draft_id" value="{{ old('draft_id', $draft?->key ?? \Illuminate\Support\Str::uuid()) }}"><input type="hidden" name="booking_key" value="{{ old('booking_key', \Illuminate\Support\Str::uuid()) }}">
<div class="split"><div>
<section class="card"><div class="card-head"><h2>{{ __('Branch route') }}</h2><span class="badge">YGN ↔ SG</span></div><div class="card-body field-grid">
<label>{{ __('Origin branch') }}<select name="origin_branch_id" data-origin-branch required>
@foreach($branches as $branch) @if(auth()->user()->isAdmin() || auth()->user()->branch_id === $branch->id)
<option value="{{ $branch->id }}" @selected(old('origin_branch_id', request('origin_branch_id', auth()->user()->branch_id ?? $branches->first()->id)) == $branch->id)>{{ $branch->code }} · {{ __($branch->name) }}</option>
@endif @endforeach
</select></label>
<label>{{ __('Destination branch') }}<select name="destination_branch_id" data-destination-branch required>
@foreach($branches as $branch)<option value="{{ $branch->id }}" @selected(old('destination_branch_id', $branches->firstWhere('id', '!=', auth()->user()->branch_id ?? $branches->first()->id)?->id) == $branch->id)>{{ $branch->code }} · {{ __($branch->name) }}</option>@endforeach
</select></label>
<p class="help full">{{ __('The receiving branch sees the same parcel and tracking number. Flight details are assigned at the dispatch desk.') }}</p>
</div></section>
<section class="card"><div class="card-head"><h2>{{ __('01 · Sender & recipient') }}</h2></div><div class="card-body field-grid">
<label>{{ __('Customer name') }}<input name="customer_name" value="{{ old('customer_name') }}" maxlength="150" required></label>
<label>{{ __('Customer phone') }}<input name="customer_phone" value="{{ old('customer_phone') }}" maxlength="50" required></label>
<label class="full">{{ __('Customer address') }}<input name="customer_address" value="{{ old('customer_address') }}" maxlength="2000"></label>
<label>{{ __('Recipient name') }}<input name="recipient_name" value="{{ old('recipient_name') }}" maxlength="150" required></label>
<label>{{ __('Recipient phone') }}<input name="recipient_phone" value="{{ old('recipient_phone') }}" maxlength="50" required></label>
<label class="full">{{ __('Recipient address (optional)') }}<textarea name="recipient_address">{{ old('recipient_address') }}</textarea></label>
</div></section>
<section class="card"><div class="card-head"><h2>{{ __('02 · Parcel details') }}</h2></div><div class="card-body field-grid">
<label class="full">{{ __('Storage location') }}<input name="storage_location" value="{{ old('storage_location', 'Receiving area') }}" maxlength="100" required><span class="help">{{ __('Example: Rack A / Shelf 2. Booking receives the parcel into origin branch storage.') }}</span></label>
<label>{{ __('Shipment date') }}<input type="date" name="shipment_date" value="{{ old('shipment_date', \App\Services\BranchAccess::todayFor()) }}" required></label>
<label>{{ __('Quantity / pieces') }}<input type="number" name="quantity" value="{{ old('quantity', 1) }}" min="1" max="10000" required></label>
<label>{{ __('Actual weight (kg)') }}<input type="number" name="actual_weight" value="{{ old('actual_weight') }}" min="0.001" max="99999.999" step="0.001" required></label>
@include('entry-draft-photos')
@include('shipments.photo-input', ['remaining' => 5 - count($draft?->photos ?? [])])
<label class="full">{{ __('Parcel description') }}<textarea name="description" required>{{ old('description') }}</textarea></label>
</div></section></div><div><section class="card"><div class="card-head"><h2>{{ __('03 · Shipment charges') }}</h2></div><div class="card-body">
<p class="subtle">{{ __('Enter the agreed shipment charge manually. MMK and SGD are kept separately; no automatic currency conversion.') }}</p>
<label>{{ __('Currency') }}<select name="currency" required>@foreach(['MMK','SGD'] as $code)<option value="{{ $code }}" @selected(old('currency', auth()->user()->branch?->code === 'SG' ? 'SGD' : 'MMK') === $code)>{{ $code }}</option>@endforeach</select></label>
<label class="section-gap">{{ __('Agreed shipment charge') }}<input type="number" name="price" value="{{ old('price') }}" min="0.01" max="999999999.99" step="0.01" required></label>
<p class="help">{{ __('The saved currency and charge cannot be changed. Payments are recorded in this same currency.') }}</p>
<p class="help">{{ __('Missing details? Save a draft and finish later. No charges are recorded until booking is confirmed.') }}</p><div class="form-actions"><button type="submit" class="button secondary" formaction="{{ route('entry-drafts.store', ['kind' => 'booking']) }}" formnovalidate>{{ __('Save draft') }}</button><button class="button">{{ __('Confirm booking →') }}</button></div>
</div></section></div></div></form>
@endsection

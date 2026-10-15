<form method="post" action="{{ route('settings.destination') }}">
@csrf @if($destination->exists)<input type="hidden" name="destination_id" value="{{ $destination->id }}">@endif
<div class="field-grid">
<label>
{{ __('Country') }}<input name="country" value="{{ $destination->country }}" maxlength="100" required>
</label>
<label>
{{ __('Status') }}
<select name="active">
<option value="1" @selected($destination->
active??true)>{{ __('Active') }}
</option>
<option value="0" @selected($destination->
exists&&!$destination->active)>{{ __('Inactive') }}
</option>
</select>
</label>
@foreach(['base_charge'=>__('Base charge'),'per_weight'=>__('Rate per ').$settings->weight_unit,'minimum_charge'=>__('Minimum charge'),'additional_charge'=>__('Additional charge')] as $field=>$label)
<label>
{{ __($label) }} ({{ $settings->currency }})<input type="number" min="0" max="999999999.99" step="0.01" name="{{ $field }}" value="{{ $destination->rate?->$field??'0.00' }}" required>
</label>
@endforeach
</div>
<div class="form-actions">
<button class="button">{{ __('Save destination & rate') }}</button>
</div>
</form>

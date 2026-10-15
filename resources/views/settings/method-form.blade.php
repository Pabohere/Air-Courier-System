<form method="post" action="{{ route('settings.method') }}">
@csrf @if($method->exists)<input type="hidden" name="method_id" value="{{ $method->id }}">@endif
<div class="field-grid">
<label>
{{ __('Name') }}<input name="name" value="{{ $method->name }}" maxlength="100" required>
</label>
<label>
{{ __('Status') }}
<select name="active">
<option value="1" @selected($method->
active??true)>{{ __('Active') }}
</option>
<option value="0" @selected($method->
exists&&!$method->active)>{{ __('Inactive') }}
</option>
</select>
</label>
</div>
<div class="form-actions">
<button class="button">{{ __('Save method') }}</button>
</div>
</form>

<form method="post" action="{{ route('settings.method') }}">
@csrf @if($method->exists)<input type="hidden" name="method_id" value="{{ $method->id }}">@endif
<div class="field-grid">
<label>
Name<input name="name" value="{{ $method->name }}" maxlength="100" required>
</label>
<label>
Status
<select name="active">
<option value="1" @selected($method->
active??true)>Active
</option>
<option value="0" @selected($method->
exists&&!$method->active)>Inactive
</option>
</select>
</label>
</div>
<div class="form-actions">
<button class="button">Save method</button>
</div>
</form>

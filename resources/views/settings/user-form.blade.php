<form method="post" action="{{ route('settings.user') }}">
@csrf @if($staff->exists)<input type="hidden" name="user_id" value="{{ $staff->id }}">@endif
<div class="field-grid">
<label>
Name<input name="name" value="{{ $staff->name }}" maxlength="150" required>
</label>
<label>
Email<input name="email" type="email" value="{{ $staff->email }}" required>
</label>
<label>
Role
<select name="role">
<option value="staff" @selected($staff->
role==='staff')>Staff
</option>
<option value="admin" @selected($staff->
role==='admin')>Admin
</option>
</select>
</label>
<label>
Status
<select name="active">
<option value="1" @selected($staff->
active??true)>Active
</option>
<option value="0" @selected($staff->
exists&&!$staff->active)>Inactive
</option>
</select>
</label>
<label>
{{ $staff->exists?'New password (leave blank to keep)':'Password' }}<input name="password" type="password" autocomplete="new-password" minlength="12" @required(!$staff->exists)>
</label>
<label>
Confirm password<input name="password_confirmation" type="password" autocomplete="new-password">
</label>
</div>
<p class="help">At least 12 characters, including letters and numbers. Password changes end existing database sessions.</p>
<div class="form-actions">
<button class="button">Save account</button>
</div>
</form>

@props(['branchId' => null, 'currency'])
@php($branches = \App\Models\Branch::orderBy('id')->get())
<label>{{ __('Branch') }}
<select name="branch_id">
@if(auth()->user()->isAdmin())<option value="">{{ __('All branches') }}</option>@endif
@foreach($branches as $scopeBranch)
@if(auth()->user()->isAdmin() || auth()->user()->branch_id === $scopeBranch->id)
<option value="{{ $scopeBranch->id }}" @selected($branchId == $scopeBranch->id)>{{ $scopeBranch->code }} · {{ __($scopeBranch->name) }}</option>
@endif
@endforeach
</select></label>
<label>{{ __('Currency') }}<select name="currency">
@foreach(array_unique(['MMK', 'SGD', 'USD', $currency]) as $code)<option value="{{ $code }}" @selected($currency === $code)>{{ $code }}</option>@endforeach
</select></label>

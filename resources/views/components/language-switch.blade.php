<form method="post" action="{{ route('language.update') }}" class="language-switch" role="group" aria-label="{{ __('Language') }}">
 @csrf
 <input type="hidden" name="return_to" value="{{ request()->getRequestUri() }}">
 <button type="submit" name="locale" value="my" lang="my" aria-pressed="{{ app()->getLocale()==='my'?'true':'false' }}">မြန်မာ</button>
 <button type="submit" name="locale" value="en" lang="en" aria-pressed="{{ app()->getLocale()==='en'?'true':'false' }}">English</button>
</form>

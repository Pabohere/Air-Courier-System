<nav class="mobile-nav" aria-label="{{ __('Mobile navigation') }}">
<x-task-links />
<button type="button" @if(!request()->routeIs('dashboard*', 'shipments.*', 'dispatches.index', 'dispatches.show')) aria-current="page" @endif data-mobile-menu-open aria-expanded="false" aria-haspopup="dialog" aria-controls="mobile-workspace-menu"><x-workspace-icon name="more" /><span class="workspace-link-label">{{ __('More') }}</span></button>
</nav>
<dialog id="mobile-workspace-menu" class="mobile-menu" aria-labelledby="mobile-menu-title">
<div class="mobile-menu-heading"><h2 id="mobile-menu-title">{{ __('Menu') }}</h2><button type="button" class="button secondary" data-mobile-menu-close aria-label="{{ __('Close menu') }}" autofocus>×</button></div>
<div class="mobile-account"><strong>{{ auth()->user()->name }}</strong><small>{{ __(ucfirst(auth()->user()->role)) }} · {{ auth()->user()->isAdmin() ? session('dashboard_branch_code', 'YGN') : auth()->user()->branch?->code }}</small></div>
<nav class="mobile-menu-links" aria-label="{{ __('Main menu') }}">
<x-task-links secondary />
@can('admin')
<a href="{{ route('settings.index') }}" @if(request()->routeIs('settings.*')) aria-current="page" @endif><x-workspace-icon name="settings" /><span class="workspace-link-label">{{ __('System settings') }}</span></a>
@endcan
</nav>
<div class="mobile-preferences"><x-language-switch /><x-theme-switch /></div>
<div class="pwa-actions"><button type="button" class="button secondary" data-pwa-install hidden>{{ __('Install app') }}</button></div>
<form method="post" action="{{ route('logout') }}">@csrf<button class="button secondary mobile-logout">{{ __('Log out') }}</button></form>
</dialog>

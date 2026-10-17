<nav class="mobile-nav" aria-label="{{ __('Mobile navigation') }}">
@foreach(['dashboard'=>[__('Home'),'⌂'],'inventory.index'=>[__('Storage'),'▦'],'dispatches.index'=>[__('Trips'),'⇄'],'reports.index'=>[__('Books'),'▤']] as $route=>$item)
<a href="{{ route($route) }}" @if(request()->routeIs($route === 'dashboard' ? 'dashboard*' : explode('.',$route)[0].'*')) aria-current="page" @endif><span aria-hidden="true">{{ $item[1] }}</span><span>{{ $item[0] }}</span></a>
@endforeach
<button type="button" data-mobile-menu-open aria-haspopup="dialog" aria-controls="mobile-workspace-menu"><span aria-hidden="true">☰</span><span>{{ __('Menu') }}</span></button>
</nav>
<dialog id="mobile-workspace-menu" class="mobile-menu" aria-labelledby="mobile-menu-title">
<div class="mobile-menu-heading"><h2 id="mobile-menu-title">{{ __('Menu') }}</h2><button type="button" class="button secondary" data-mobile-menu-close aria-label="{{ __('Close menu') }}" autofocus>×</button></div>
<div class="mobile-account"><strong>{{ auth()->user()->name }}</strong><small>{{ __(ucfirst(auth()->user()->role)) }} · {{ auth()->user()->isAdmin() ? session('dashboard_branch_code', 'YGN') : auth()->user()->branch?->code }}</small></div>
<nav class="mobile-menu-links" aria-label="{{ __('Main menu') }}">
@foreach(['shipments.index'=>[__('All parcel records'),'▣'],'customers.index'=>[__('Customers'),'♧'],'branches.index'=>[__('Daily work'),'⇆']] as $route=>$item)
<a href="{{ route($route) }}" @if(request()->routeIs(explode('.',$route)[0].'*')) aria-current="page" @endif><span aria-hidden="true">{{ $item[1] }}</span>{{ $item[0] }}<span aria-hidden="true">›</span></a>
@endforeach
@can('admin')
<a href="{{ route('settings.index') }}" @if(request()->routeIs('settings.*')) aria-current="page" @endif><span aria-hidden="true">⚙</span>{{ __('System settings') }}<span aria-hidden="true">›</span></a>
@endcan
</nav>
<div class="mobile-preferences"><x-language-switch /><x-theme-switch /></div>
<div class="pwa-actions"><button type="button" class="button secondary" data-pwa-install hidden>{{ __('Install app') }}</button></div>
<form method="post" action="{{ route('logout') }}">@csrf<button class="button secondary mobile-logout">{{ __('Log out') }}</button></form>
</dialog>

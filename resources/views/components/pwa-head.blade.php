<link rel="manifest" href="{{ route('pwa.manifest') }}">
<meta name="theme-color" content="#2d5cff">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="Standard">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<link rel="apple-touch-icon" sizes="180x180" href="{{ asset('images/apple-touch-icon.png') }}">
<script src="{{ asset('js/pwa.js') }}?v={{ filemtime(public_path('js/pwa.js')) }}" data-worker-url="{{ asset('service-worker.js') }}" defer></script>
<script src="{{ asset('js/navigation.js') }}?v={{ filemtime(public_path('js/navigation.js')) }}" defer></script>

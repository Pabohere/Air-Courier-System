@props(['name'])

<span class="workspace-icon workspace-icon--{{ $name }}" aria-hidden="true">
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" focusable="false">
        @switch($name)
            @case('home')
                <path d="m3 11 9-8 9 8M5 10v10h5v-5h4v5h5V10"/>
                @break
            @case('receive')
                <path d="M12 3v11m-4-4 4 4 4-4M4 14v5a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-5"/>
                @break
            @case('send')
                <path d="m21 3-7 18-3.5-7.5L3 10 21 3ZM21 3 10.5 13.5"/>
                @break
            @case('find')
                <circle cx="10.5" cy="10.5" r="5.5"/><path d="m15 15 5 5"/>
                @break
            @case('flights')
                <rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 3v3m6-3v3M8 10h8m-8 4h8m-8 4h4"/>
                @break
            @case('storage')
                <path d="m12 3 8 4.5v9L12 21l-8-4.5v-9L12 3Zm-8 4.5L12 12l8-4.5M12 12v9M8 5.25l8 4.5"/>
                @break
            @case('books')
                <path d="M5 5h13a2 2 0 0 1 2 2v2m-15-4a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h13a2 2 0 0 0 2-2v-2M3 8h17M16 10h5a1 1 0 0 1 1 1v4a1 1 0 0 1-1 1h-5a3 3 0 0 1 0-6Zm1 3h.01"/>
                @break
            @case('customers')
                <circle cx="10" cy="8" r="3"/><path d="M4 20v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2M17 5a3 3 0 0 1 0 6m1 3a4 4 0 0 1 3 4v2"/>
                @break
            @case('daily')
                <path d="M4 8h15l-4-4m5 12H5l4 4"/>
                @break
            @case('settings')
                <path d="M4 6h5m4 0h7M4 12h10m4 0h2M4 18h3m4 0h9M9 4v4m5 2v4m-7 2v4"/>
                @break
            @case('more')
                <path d="M5 12h.01M12 12h.01M19 12h.01" stroke-width="3"/>
                @break
        @endswitch
    </svg>
</span>

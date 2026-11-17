@props(['name'])
<svg {{ $attributes->class('ui-icon') }} xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($name)
        @case('menu')
            <path d="M4 6h16M4 12h16M4 18h16"/>
            @break
        @case('search')
            <circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4.5 4.5"/>
            @break
        @case('journal')
            <rect x="4" y="3" width="16" height="18" rx="2"/><path d="M8 3v18m4-13h4m-4 4h4"/>
            @break
        @case('calendar')
            <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M7 3v4m10-4v4M3 10h18m-13 5 2 2 5-5"/>
            @break
        @case('profile')
            <rect x="2" y="5" width="20" height="15" rx="2"/><circle cx="8" cy="11" r="2"/><path d="M5 16c1-3 5-3 6 0m4-5h4m-4 4h3"/>
            @break
        @case('pin')
            <path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>
            @break
        @case('check')
            <circle cx="12" cy="12" r="9"/><path d="m8 12.5 2.7 2.7L16.5 9.5"/>
            @break
        @case('print')
            <path d="M7 8V3h10v5M7 17H5a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="7" y="14" width="10" height="7" rx="1"/>
            @break
        @case('school')
            <path d="M3 10 12 5l9 5-9 5-9-5z"/><path d="M7 12.5V17c3 2 7 2 10 0v-4.5"/>
            @break
        @case('teacher')
            <circle cx="9" cy="7" r="3"/><path d="M3 20c0-3.3 2.7-6 6-6s6 2.7 6 6"/><path d="m15 11 2 2 4-4"/>
            @break
        @case('phone')
            <rect x="6" y="2" width="12" height="20" rx="2.5"/><path d="M10.5 18.5h3"/>
            @break
        @case('building')
            <path d="M4 21V5a1 1 0 0 1 1-1h9a1 1 0 0 1 1 1v16M15 10h4a1 1 0 0 1 1 1v10M2 21h20M8 8h3m-3 4h3m-3 4h3"/>
            @break
        @case('shield')
            <path d="M12 3 5 6v5.5c0 4.4 3 8.2 7 9.5 4-1.3 7-5.1 7-9.5V6l-7-3z"/><path d="m9 12 2 2 4-4"/>
            @break
        @case('bell')
            <path d="M6 16V11a6 6 0 0 1 12 0v5l1.5 2h-15L6 16z"/><path d="M10 20a2 2 0 0 0 4 0"/>
            @break
        @case('alert')
            <path d="M12 3 2 20h20L12 3z"/><path d="M12 10v4m0 3h.01"/>
            @break
        @case('award')
            <circle cx="12" cy="9" r="6"/><path d="m8.5 14-1.5 7 5-3 5 3-1.5-7"/>
            @break
        @case('link')
            <path d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1"/><path d="M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>
            @break
    @endswitch
</svg>

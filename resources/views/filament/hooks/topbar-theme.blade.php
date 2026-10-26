{{-- Light/Dark toggle in the top bar, before the notifications bell --}}
@if (filament()->auth()->check())
    <x-filament-panels::theme-switcher compact />
@endif

{{-- Replaces the user dropdown: the avatar is a direct link to the profile (rendered after the notifications bell) --}}
@php
    $user = filament()->auth()->user();
    $isOnProfile = request()->routeIs(\App\Filament\User\Pages\Profile::getRouteName());
@endphp

@if ($user)
    <a
        href="{{ \App\Filament\User\Pages\Profile::getUrl() }}"
        aria-label="My profile"
        @if ($isOnProfile) aria-current="page" @endif
        x-tooltip="{
            content: 'My profile',
            theme: $store.theme,
        }"
        @class(['bg-avatar-link', 'is-active' => $isOnProfile])
    >
        <x-filament-panels::avatar.user :user="$user" loading="lazy" />
    </a>
@endif

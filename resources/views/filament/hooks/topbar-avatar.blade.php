{{-- The avatar is a direct link to the panel's profile page (rendered after the notifications bell). Expects $profilePage. --}}
@php
    $user = filament()->auth()->user();
    $isOnProfile = request()->routeIs($profilePage::getRouteName());
@endphp

@if ($user)
    <a
        href="{{ $profilePage::getUrl() }}"
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

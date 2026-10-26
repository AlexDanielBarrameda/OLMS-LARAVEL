@php
    $user = filament()->auth()->user();
@endphp

<x-filament-panels::page>
    <x-filament::section>
        <div class="bg-profile-card">
            <x-filament-panels::avatar.user :user="$user" size="lg" class="bg-profile-avatar" />

            <div class="bg-profile-meta">
                <div class="bg-profile-name-row">
                    <h2 class="bg-profile-name">{{ $user->name }}</h2>

                    <x-filament::badge :color="$user->isSuperAdmin() ? 'primary' : 'info'">
                        {{ $user->roleLabel() }}
                    </x-filament::badge>
                </div>

                <p class="bg-profile-detail">{{ $user->email }}</p>
                <p class="bg-profile-detail">Member since {{ $user->created_at?->format('F Y') }}</p>
            </div>
        </div>
    </x-filament::section>

    @include('filament.partials.account-actions')
</x-filament-panels::page>

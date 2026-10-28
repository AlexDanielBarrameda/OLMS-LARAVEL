<x-filament-widgets::widget>
    <x-filament::section>
        <div class="bg-profile">
            <x-filament-panels::avatar.user :user="$user" class="bg-profile-avatar" />

            <div>
                <h2 class="bg-profile-name">{{ $user->name }}</h2>

                <ul class="bg-profile-facts">
                    <li>
                        <x-heroicon-m-envelope aria-hidden="true" />
                        {{ $user->email }}
                    </li>
                    <li>
                        <x-heroicon-m-book-open aria-hidden="true" />
                        {{ $currentlyBorrowed }} {{ str('book')->plural($currentlyBorrowed) }} currently borrowed
                    </li>
                    <li>
                        <x-heroicon-m-clock aria-hidden="true" />
                        Member since {{ $user->created_at->format('F Y') }}
                    </li>
                </ul>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

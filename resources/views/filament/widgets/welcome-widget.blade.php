<x-filament-widgets::widget>
    <x-filament::section>
        <div class="bg-welcome">
            <div class="bg-welcome-who">
                <x-filament-panels::avatar.user :user="$user" size="lg" />

                <div class="bg-welcome-text">
                    <h2 class="bg-welcome-greeting">{{ $greeting }}, {{ str($user->name)->before(' ') }}</h2>
                    <p class="bg-welcome-sub">{{ $user->roleLabel() }} · {{ now()->format('l, F j') }}</p>
                </div>
            </div>

            @if ($pendingLoans || $pendingBookRequests)
                <div class="bg-welcome-actions">
                    @if ($pendingLoans)
                        <x-filament::button
                            tag="a"
                            :href="$pendingLoansUrl"
                            color="warning"
                            icon="heroicon-m-inbox-arrow-down"
                        >
                            Review {{ $pendingLoans }} borrow {{ str('request')->plural($pendingLoans) }}
                        </x-filament::button>
                    @endif

                    @if ($pendingBookRequests)
                        <x-filament::button
                            tag="a"
                            :href="$bookRequestsUrl"
                            color="gray"
                            icon="heroicon-m-clipboard-document-list"
                        >
                            {{ $pendingBookRequests }} book {{ str('request')->plural($pendingBookRequests) }}
                        </x-filament::button>
                    @endif
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>

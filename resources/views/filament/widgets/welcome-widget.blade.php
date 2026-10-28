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

            @if ($pendingLoans || $finesToVerify || $returnsToVerify || $paymentsToCheck || $refundsToProcess || $pendingBookRequests)
                <div class="bg-welcome-actions">
                    @if ($returnsToVerify)
                        <x-filament::button
                            tag="a"
                            :href="$returnsUrl"
                            color="info"
                            icon="heroicon-m-arrow-uturn-left"
                        >
                            Check in {{ $returnsToVerify }} {{ str('return')->plural($returnsToVerify) }}
                        </x-filament::button>
                    @endif

                    @if ($paymentsToCheck)
                        <x-filament::button
                            tag="a"
                            :href="$paymentChecksUrl"
                            color="warning"
                            outlined
                            icon="heroicon-m-magnifying-glass"
                        >
                            Check {{ $paymentsToCheck }} {{ str('payment')->plural($paymentsToCheck) }}
                        </x-filament::button>
                    @endif

                    @if ($refundsToProcess)
                        <x-filament::button
                            tag="a"
                            :href="$refundsUrl"
                            color="warning"
                            outlined
                            icon="heroicon-m-banknotes"
                        >
                            Process {{ $refundsToProcess }} {{ str('refund')->plural($refundsToProcess) }}
                        </x-filament::button>
                    @endif

                    @if ($finesToVerify)
                        <x-filament::button
                            tag="a"
                            :href="$finesUrl"
                            color="info"
                            icon="heroicon-m-receipt-percent"
                        >
                            Verify {{ $finesToVerify }} fine {{ str('payment')->plural($finesToVerify) }}
                        </x-filament::button>
                    @endif

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

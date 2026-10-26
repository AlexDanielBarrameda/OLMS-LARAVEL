{{-- Password + Sign out cards, shared by the student and staff profile pages --}}
<div class="bg-account">
    <x-filament::section
        icon="heroicon-o-lock-closed"
        heading="Password"
    >
        <div class="bg-account-row">
            <p class="bg-account-text">
                Use at least 8 characters, and avoid a password you use on other sites.
            </p>

            {{ $this->changePasswordAction }}
        </div>
    </x-filament::section>

    <x-filament::section
        icon="heroicon-o-arrow-left-start-on-rectangle"
        heading="Sign out"
    >
        <div class="bg-account-row">
            <p class="bg-account-text">
                End your session on this device. You'll need your password to sign back in.
            </p>

            <form method="POST" action="{{ filament()->getLogoutUrl() }}">
                @csrf

                <x-filament::button
                    type="submit"
                    color="danger"
                    icon="heroicon-m-arrow-left-start-on-rectangle"
                >
                    Sign out
                </x-filament::button>
            </form>
        </div>
    </x-filament::section>
</div>

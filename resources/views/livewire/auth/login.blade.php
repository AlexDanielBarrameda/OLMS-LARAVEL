@php
    $input = 'w-full px-4 py-2.5 rounded-xl border border-zinc-200 bg-white text-zinc-900 placeholder:text-zinc-400 outline-none transition-[border-color,box-shadow] duration-150 focus:border-emerald-600 focus:ring-4 focus:ring-emerald-600/15 aria-[invalid=true]:border-red-500 aria-[invalid=true]:focus:ring-red-500/15';
    $label = 'block text-sm font-medium text-zinc-700';
    $error = 'mt-1.5 text-sm text-red-600';
    $tab = 'flex-1 py-2 rounded-[0.625rem] text-sm font-semibold transition-[background-color,color,box-shadow] duration-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700';
@endphp

<div class="min-h-screen bg-zinc-50 flex flex-col items-center justify-center p-4 font-sans text-zinc-900 selection:bg-emerald-700 selection:text-white">
    <div class="text-center mb-8">
        <div class="bg-emerald-700 rounded-2xl flex items-center justify-center mx-auto mb-4 text-white shadow-[0_8px_20px_-6px_rgb(4_120_87/0.5)]" style="width: 52px; height: 52px;">
            <x-heroicon-s-book-open class="w-7 h-7" aria-hidden="true" />
        </div>
        <h1 class="text-2xl font-semibold tracking-tight text-balance">Book Garden Library</h1>
        <p class="text-zinc-500 text-sm mt-1">Manage your reading journey</p>
    </div>

    <div class="bg-white p-8 rounded-3xl w-full max-w-md border border-zinc-200/80 shadow-[0_24px_48px_-24px_rgb(24_24_27/0.25)]">
        {{-- Segmented control: switches between signing in and creating an account --}}
        <div class="flex bg-zinc-100 p-1 rounded-xl mb-6" role="tablist" aria-label="Sign in or create an account">
            <button type="button" role="tab" id="tab-login" aria-controls="auth-form" aria-selected="{{ $isLogin ? 'true' : 'false' }}"
                wire:click="$set('isLogin', true)"
                class="{{ $tab }} {{ $isLogin ? 'bg-emerald-700 text-white shadow-[0_2px_6px_-1px_rgb(4_120_87/0.45)]' : 'text-zinc-500 hover:text-zinc-800' }}">
                Login
            </button>
            <button type="button" role="tab" id="tab-register" aria-controls="auth-form" aria-selected="{{ $isLogin ? 'false' : 'true' }}"
                wire:click="$set('isLogin', false)"
                class="{{ $tab }} {{ ! $isLogin ? 'bg-emerald-700 text-white shadow-[0_2px_6px_-1px_rgb(4_120_87/0.45)]' : 'text-zinc-500 hover:text-zinc-800' }}">
                Register
            </button>
        </div>

        <form id="auth-form" role="tabpanel" aria-labelledby="{{ $isLogin ? 'tab-login' : 'tab-register' }}" wire:submit.prevent="authenticate" class="space-y-5" novalidate>
            @if (! $isLogin)
                <div>
                    <label for="auth-name" class="{{ $label }}">Full name</label>
                    <input id="auth-name" wire:model="name" type="text" required autocomplete="name"
                        @error('name') aria-invalid="true" aria-describedby="auth-name-error" @enderror
                        class="{{ $input }} mt-1.5" placeholder="Juan Dela Cruz">
                    @error('name') <p id="auth-name-error" class="{{ $error }}">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label for="auth-email" class="{{ $label }}">Email address</label>
                <input id="auth-email" wire:model="email" type="email" required autocomplete="email"
                    @error('email') aria-invalid="true" aria-describedby="auth-email-error" @enderror
                    class="{{ $input }} mt-1.5" placeholder="you@example.com">
                @error('email') <p id="auth-email-error" class="{{ $error }}" role="alert">{{ $message }}</p> @enderror
            </div>

            <div x-data="{ showPassword: false }">
                <label for="auth-password" class="{{ $label }}">Password</label>
                <div class="relative mt-1.5">
                    <input id="auth-password" wire:model="password" x-bind:type="showPassword ? 'text' : 'password'" required
                        autocomplete="{{ $isLogin ? 'current-password' : 'new-password' }}"
                        aria-describedby="{{ $errors->has('password') ? 'auth-password-error' : (! $isLogin ? 'auth-password-hint' : '') }}"
                        @error('password') aria-invalid="true" @enderror
                        class="{{ $input }} pr-11" placeholder="••••••••">

                    <button type="button" @click="showPassword = !showPassword"
                        x-bind:aria-label="showPassword ? 'Hide password' : 'Show password'" x-bind:aria-pressed="showPassword"
                        class="absolute inset-y-0 right-0 px-3 flex items-center rounded-r-xl text-zinc-400 hover:text-emerald-700 transition-colors focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-emerald-700">
                        <x-heroicon-o-eye x-show="!showPassword" class="w-5 h-5" aria-hidden="true" />
                        <x-heroicon-o-eye-slash x-cloak x-show="showPassword" class="w-5 h-5" aria-hidden="true" />
                    </button>
                </div>
                @error('password')
                    <p id="auth-password-error" class="{{ $error }}">{{ $message }}</p>
                @else
                    @if (! $isLogin)
                        <p id="auth-password-hint" class="mt-1.5 text-xs text-zinc-500">At least 8 characters.</p>
                    @endif
                @enderror
            </div>

            @if (! $isLogin)
                <div x-data="{ showPassword: false }">
                    <label for="auth-password-confirmation" class="{{ $label }}">Confirm password</label>
                    <div class="relative mt-1.5">
                        <input id="auth-password-confirmation" wire:model="password_confirmation" x-bind:type="showPassword ? 'text' : 'password'" required
                            autocomplete="new-password"
                            class="{{ $input }} pr-11" placeholder="••••••••">

                        <button type="button" @click="showPassword = !showPassword"
                            x-bind:aria-label="showPassword ? 'Hide password' : 'Show password'" x-bind:aria-pressed="showPassword"
                            class="absolute inset-y-0 right-0 px-3 flex items-center rounded-r-xl text-zinc-400 hover:text-emerald-700 transition-colors focus-visible:outline-2 focus-visible:outline-offset-[-2px] focus-visible:outline-emerald-700">
                            <x-heroicon-o-eye x-show="!showPassword" class="w-5 h-5" aria-hidden="true" />
                            <x-heroicon-o-eye-slash x-cloak x-show="showPassword" class="w-5 h-5" aria-hidden="true" />
                        </button>
                    </div>
                </div>
            @endif

            <button type="submit" wire:loading.attr="disabled" wire:target="authenticate"
                class="w-full bg-emerald-700 text-white py-3 rounded-xl font-semibold text-sm shadow-[0_6px_16px_-6px_rgb(4_120_87/0.55)] hover:bg-emerald-800 active:scale-[0.98] disabled:opacity-60 transition-[background-color,transform] duration-150 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-emerald-700">
                {{ $isLogin ? 'Sign In to Account' : 'Create New Account' }}
            </button>
        </form>
    </div>
</div>

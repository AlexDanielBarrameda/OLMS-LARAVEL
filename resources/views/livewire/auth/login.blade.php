<div class="min-h-screen bg-gray-50 flex flex-col items-center justify-center p-4 font-sans">
    <div class="text-center mb-8">
        <div class="bg-emerald-700 rounded-lg flex items-center justify-center mx-auto mb-4 text-white shadow-lg" style="width: 52px; height: 52px;">
            <x-heroicon-s-book-open style="width: 32px; height: 32px;"/>
        </div>
        <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Book Garden Library</h1>
        <p class="text-gray-500 text-sm">Manage your reading journey</p>
    </div>

    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-md border border-gray-100">
        <div class="flex bg-gray-100 p-1 rounded-xl mb-6">
            <button wire:click="$set('isLogin', true)" 
                class="flex-1 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200 {{ $isLogin ? 'bg-emerald-700 text-white shadow-md' : 'text-gray-500 hover:text-gray-700' }}">
                Login
            </button>
            <button wire:click="$set('isLogin', false)" 
                class="flex-1 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200 {{ !$isLogin ? 'bg-emerald-700 text-white shadow-md' : 'text-gray-500 hover:text-gray-700' }}">
                Register
            </button>
        </div>

        <form wire:submit.prevent="authenticate" class="space-y-5">
            @if(!$isLogin)
                <div>
                    <label class="text-xs font-bold text-gray-700 uppercase tracking-wider">Full Name</label>
                    <input wire:model="name" type="text" required
                        class="w-full mt-1 px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all"
                        placeholder="Juan Dela Cruz">
                    @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label class="text-xs font-bold text-gray-700 uppercase tracking-wider">Email Address</label>
                <input wire:model="email" type="email" required
                    class="w-full mt-1 px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all"
                    placeholder="you@example.com">
                @error('email') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div x-data="{ showPassword: false }">
                <label class="text-xs font-bold text-gray-700 uppercase tracking-wider">Password</label>
                <div class="relative mt-1">
                    <input wire:model="password" x-bind:type="showPassword ? 'text' : 'password'" required
                        class="w-full px-4 py-2.5 border border-gray-200 rounded-lg focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 outline-none transition-all pr-10"
                        placeholder="••••••••">
                    
                    <button type="button" @click="showPassword = !showPassword" 
                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-emerald-700 transition-colors focus:outline-none">
                        
                        <svg x-show="!showPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>

                        <svg x-cloak x-show="showPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
                @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @else
                    @if(!$isLogin)
                        <p class="mt-1 text-xs text-gray-500">At least 8 characters.</p>
                    @endif
                @enderror
            </div>

            @if($isLogin)
                @endif

            <button type="submit" 
                class="w-full bg-emerald-700 text-white py-3.5 rounded-lg font-bold text-sm shadow-lg shadow-emerald-200 hover:bg-emerald-800 active:scale-[0.98] transition-all">
                {{ $isLogin ? 'Sign In to Account' : 'Create New Account' }}
            </button>
        </form>
    </div>
</div>
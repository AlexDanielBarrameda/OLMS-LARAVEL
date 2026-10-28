<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

// The one sign-in / sign-up page for everyone (the panels' own login and registration pages are off)
#[Layout('components.layouts.app')]
class Login extends Component
{
    public bool $isLogin = true;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    // Failed sign-ins allowed per email + IP, and sign-ups per IP, each per minute
    public const MAX_LOGIN_ATTEMPTS = 5;

    // Failed sign-ins per IP across all emails (credential stuffing). Generous, since a school lab shares one IP.
    public const MAX_LOGIN_ATTEMPTS_PER_IP = 20;

    public const MAX_REGISTRATIONS = 5;

    // The Login/Register tabs use $set('isLogin'), so clear errors from the other tab here
    public function updatedIsLogin(): void
    {
        $this->resetErrorBag();
    }

    public function authenticate()
    {
        return $this->isLogin ? $this->signIn() : $this->signUp();
    }

    protected function signIn()
    {
        $this->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Slow down password guessing: lock this email (from this IP) out after too many failures,
        // and this IP out after too many failures across any emails
        $this->ensureNotRateLimited($key = $this->loginThrottleKey(), static::MAX_LOGIN_ATTEMPTS);
        $this->ensureNotRateLimited($ipKey = 'login-ip:'.request()->ip(), static::MAX_LOGIN_ATTEMPTS_PER_IP);

        $credentials = ['email' => $this->email, 'password' => $this->password];

        // Deactivated accounts can't sign in, even with the right password
        if (Auth::attemptWhen($credentials, fn (User $user): bool => ! $user->isInactive())) {
            RateLimiter::clear($key);
            session()->regenerate();
            $user = Auth::user();

            Notification::make()
                ->title('Login Successful')
                ->body("Welcome back, {$user->name}!")
                ->success()
                ->send();

            return redirect()->to($user->isSuperAdmin() || $user->isLibrarian() ? '/admin' : '/user');
        }

        // Only say the account is deactivated to someone who knows its password
        $user = Auth::getLastAttempted();

        if ($user instanceof User && $user->isInactive() && Auth::getProvider()->validateCredentials($user, $credentials)) {
            $this->addError('email', 'This account has been deactivated. Please contact the library.');

            return null;
        }

        RateLimiter::hit($key);
        RateLimiter::hit($ipKey);

        Notification::make()
            ->title('Invalid credentials')
            ->body('The email or password you entered is incorrect.')
            ->danger()
            ->send();

        return null;
    }

    protected function signUp()
    {
        // Every attempt counts (not just successful sign-ups), which also limits probing which emails are taken
        $this->ensureNotRateLimited($key = 'register:'.request()->ip(), static::MAX_REGISTRATIONS);
        RateLimiter::hit($key);

        $this->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            // Must match password_confirmation, so a typo can't lock the user out of a new account
            'password' => 'required|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'role' => 'student',
            'status' => 'active',
        ]);

        Auth::login($user);
        session()->regenerate();

        Notification::make()
            ->title('Account Created!')
            ->body("Welcome to Book Garden Library, {$user->name}!")
            ->success()
            ->send();

        return redirect()->to('/user');
    }

    protected function loginThrottleKey(): string
    {
        return 'login:'.Str::transliterate(Str::lower($this->email)).'|'.request()->ip();
    }

    // Shows "Too many attempts" under the email field until the minute is up
    protected function ensureNotRateLimited(string $key, int $maxAttempts): void
    {
        if (! RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            return;
        }

        event(new Lockout(request()));

        throw ValidationException::withMessages([
            'email' => 'Too many attempts. Please try again in '.RateLimiter::availableIn($key).' seconds.',
        ]);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}

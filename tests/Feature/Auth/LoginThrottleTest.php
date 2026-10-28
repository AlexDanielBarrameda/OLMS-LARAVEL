<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('locks an email out after 5 failed sign-ins, even with the right password', function () {
    $user = User::factory()->create(['email' => 'student@example.com', 'password' => 'correct-password']);

    foreach (range(1, Login::MAX_LOGIN_ATTEMPTS) as $attempt) {
        Livewire::test(Login::class)
            ->set('email', 'Student@Example.com')
            ->set('password', 'wrong-password')
            ->call('authenticate')
            ->assertHasNoErrors();
    }

    Livewire::test(Login::class)
        ->set('email', 'student@example.com')
        ->set('password', 'correct-password')
        ->call('authenticate')
        ->assertHasErrors(['email'])
        ->assertSee('Too many attempts');

    $this->assertGuest();

    // The lockout lifts after a minute
    $this->travel(61)->seconds();

    Livewire::test(Login::class)
        ->set('email', 'student@example.com')
        ->set('password', 'correct-password')
        ->call('authenticate')
        ->assertRedirect('/user');

    $this->assertAuthenticatedAs($user);
});

it('locks an IP out after too many failed sign-ins across different emails', function () {
    User::factory()->create(['email' => 'victim@example.com', 'password' => 'correct-password']);

    foreach (range(1, Login::MAX_LOGIN_ATTEMPTS_PER_IP) as $n) {
        Livewire::test(Login::class)
            ->set('email', "guess{$n}@example.com")
            ->set('password', 'password')
            ->call('authenticate')
            ->assertHasNoErrors();
    }

    Livewire::test(Login::class)
        ->set('email', 'victim@example.com')
        ->set('password', 'correct-password')
        ->call('authenticate')
        ->assertHasErrors(['email']);

    $this->assertGuest();
});

it('limits sign-ups from one address to 5 a minute', function () {
    foreach (range(1, Login::MAX_REGISTRATIONS) as $n) {
        Livewire::test(Login::class)
            ->set('isLogin', false)
            ->set('name', "Student {$n}")
            ->set('email', "new{$n}@example.com")
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('authenticate')
            ->assertHasNoErrors();

        auth()->logout();
    }

    Livewire::test(Login::class)
        ->set('isLogin', false)
        ->set('name', 'One Too Many')
        ->set('email', 'extra@example.com')
        ->set('password', 'password123')
        ->set('password_confirmation', 'password123')
        ->call('authenticate')
        ->assertHasErrors(['email']);

    expect(User::where('email', 'extra@example.com')->exists())->toBeFalse();
});

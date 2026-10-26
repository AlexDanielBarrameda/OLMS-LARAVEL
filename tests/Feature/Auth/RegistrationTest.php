<?php

use App\Livewire\Auth\Login;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('shows a visible error when the password is too short', function () {
    Livewire::test(Login::class)
        ->set('isLogin', false)
        ->set('name', 'test acc')
        ->set('email', 'test@gmail.com')
        ->set('password', 'testacc')
        ->call('authenticate')
        ->assertHasErrors(['password' => 'min'])
        ->assertSee('The password field must be at least 8 characters.');

    expect(User::where('email', 'test@gmail.com')->exists())->toBeFalse();
});

it('shows a visible error when the email is already taken', function () {
    User::factory()->create(['email' => 'test@gmail.com']);

    Livewire::test(Login::class)
        ->set('isLogin', false)
        ->set('name', 'test acc')
        ->set('email', 'test@gmail.com')
        ->set('password', 'testacc123')
        ->call('authenticate')
        ->assertHasErrors(['email' => 'unique'])
        ->assertSee('The email has already been taken.');
});

it('clears register errors when switching back to the login tab', function () {
    Livewire::test(Login::class)
        ->set('isLogin', false)
        ->set('password', 'short')
        ->call('authenticate')
        ->assertHasErrors('password')
        ->set('isLogin', true)
        ->assertHasNoErrors();
});

it('registers a student and redirects to the user panel', function () {
    Livewire::test(Login::class)
        ->set('isLogin', false)
        ->set('name', 'test acc')
        ->set('email', 'test@gmail.com')
        ->set('password', 'testacc123')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect('/user');

    $user = User::where('email', 'test@gmail.com')->first();

    expect($user)->not->toBeNull()
        ->and($user->role)->toBe('student');

    $this->assertAuthenticatedAs($user);
});

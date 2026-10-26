<?php

use App\Filament\User\Pages\Profile;
use App\Filament\User\Pages\UserDashboard;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('user'));

    $this->user = User::factory()->create([
        'role' => 'student',
        'password' => 'old-password',
    ]);

    $this->actingAs($this->user);
});

it('links the avatar straight to the profile, with no dropdown or sign out in the top bar', function () {
    $this->get(UserDashboard::getUrl())
        ->assertOk()
        ->assertSeeInOrder(['href="'.Profile::getUrl().'"', 'class="bg-avatar-link"'], escape: false)
        ->assertDontSee('fi-user-menu', escape: false)
        ->assertDontSee('fi-topbar-item-label">Profile', escape: false)
        ->assertDontSee(filament()->getLogoutUrl(), escape: false);
});

it('marks the avatar active on the profile page', function () {
    $this->get(Profile::getUrl())
        ->assertOk()
        ->assertSee('aria-current="page"', escape: false);
});

it('offers only light and dark themes in the top bar', function () {
    $this->get(UserDashboard::getUrl())
        ->assertOk()
        ->assertSee('bg-appearance bg-appearance-compact', escape: false)
        ->assertSee('bg-segmented', escape: false)
        ->assertSee('switchTheme', escape: false)
        ->assertDontSee('fi-theme-switcher-btn', escape: false)
        ->assertSee(__('filament-panels::layout.actions.theme_switcher.dark.label'))
        ->assertDontSee(__('filament-panels::layout.actions.theme_switcher.system.label'));
});

it('shows change password and sign out on the profile page', function () {
    $this->get(Profile::getUrl())
        ->assertOk()
        ->assertSee('Change password')
        ->assertSee('Sign out')
        ->assertSee(filament()->getLogoutUrl(), escape: false);
});

it('changes the password', function () {
    Livewire::test(Profile::class)
        ->callAction('changePassword', data: [
            'current_password' => 'old-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertHasNoActionErrors()
        ->assertNotified('Password updated');

    expect(Hash::check('new-password-123', $this->user->fresh()->password))->toBeTrue();
});

it('rejects a wrong current password or mismatched confirmation', function () {
    Livewire::test(Profile::class)
        ->callAction('changePassword', data: [
            'current_password' => 'wrong-password',
            'password' => 'new-password-123',
            'password_confirmation' => 'something-else',
        ])
        ->assertHasActionErrors(['current_password', 'password']);

    expect(Hash::check('old-password', $this->user->fresh()->password))->toBeTrue();
});

it('signs out', function () {
    $this->post(filament()->getLogoutUrl())->assertRedirect();

    $this->assertGuest();
});

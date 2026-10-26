<?php

namespace App\Filament\Concerns;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rules\Password;

// "Change password" modal shared by the student and staff profile pages
trait ChangesPassword
{
    public function changePasswordAction(): Action
    {
        return Action::make('changePassword')
            ->label('Change password')
            ->icon(Heroicon::OutlinedKey)
            ->color('gray')
            ->modalIcon(Heroicon::OutlinedKey)
            ->modalHeading('Change password')
            ->modalDescription('Enter your current password, then choose a new one.')
            ->modalWidth(Width::Medium)
            ->modalSubmitActionLabel('Update password')
            ->schema([
                TextInput::make('current_password')
                    ->label('Current password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->currentPassword(),
                TextInput::make('password')
                    ->label('New password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->rule(Password::default())
                    ->different('current_password')
                    ->same('password_confirmation'),
                TextInput::make('password_confirmation')
                    ->label('Confirm new password')
                    ->password()
                    ->revealable()
                    ->required()
                    ->dehydrated(false),
            ])
            ->action(function (array $data): void {
                $user = Filament::auth()->user();

                // The "hashed" cast on User hashes this
                $user->update(['password' => $data['password']]);

                // Keep this session valid under AuthenticateSession; other sessions get signed out
                if (request()->hasSession()) {
                    request()->session()->put('password_hash_'.Filament::getAuthGuard(), $user->getAuthPassword());
                }

                Notification::make()
                    ->title('Password updated')
                    ->success()
                    ->send();
            });
    }
}

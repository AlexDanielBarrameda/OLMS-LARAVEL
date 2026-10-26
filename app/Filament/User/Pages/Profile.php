<?php

namespace App\Filament\User\Pages;

use App\Filament\Concerns\ChangesPassword;
use Filament\Pages\Page;

class Profile extends Page
{
    use ChangesPassword;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament.user.pages.profile';

    // Reached from the avatar menu, not the top navigation
    protected static bool $shouldRegisterNavigation = false;

    public function getHeading(): string
    {
        return '';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\User\Widgets\UserInfoWidget::class,
            \App\Filament\User\Widgets\ProfileStatsWidget::class,
            \App\Filament\User\Widgets\BorrowingHistoryWidget::class,
        ];
    }
}

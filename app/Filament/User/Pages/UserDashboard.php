<?php

namespace App\Filament\User\Pages;

use Filament\Pages\Page;

class UserDashboard extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';
    protected string $view = 'filament.user.pages.user-dashboard';
    protected static ?string $title = 'Browse';
    protected static ?string $navigationLabel = 'Browse';

    public function getHeading(): string
    {
        return '';
    }

    protected function getHeaderWidgets(): array
    {
        return [
            \App\Filament\User\Widgets\MyBorrowedBooksWidget::class,
            \App\Filament\User\Widgets\AvailableBooksWidget::class,
        ];
    }
}
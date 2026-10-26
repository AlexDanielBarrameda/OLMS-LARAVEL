<?php

namespace App\Filament\Pages;

class AdminDashboard extends \Filament\Pages\Dashboard
{
    // "Librarian Dashboard" for librarians, "Admin Dashboard" for super admins
    public static function dashboardName(): string
    {
        return auth()->user()?->isLibrarian() ? 'Librarian Dashboard' : 'Admin Dashboard';
    }

    public function getTitle(): string
    {
        return static::dashboardName();
    }

    public static function getNavigationLabel(): string
    {
        return static::dashboardName();
    }
}

<?php

namespace App\Filament\Pages;

class AdminDashboard extends \Filament\Pages\Dashboard
{
    // This changes the big heading at the top
    protected static ?string $title = 'Admin Dashboard';
    
    // This changes the name in the sidebar
    protected static ?string $navigationLabel = 'Admin Dashboard';
}
<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\ChangesPassword;
use Filament\Pages\Page;

// Staff account page, reached by clicking the avatar in the top bar
class Profile extends Page
{
    use ChangesPassword;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-circle';
    protected string $view = 'filament.pages.profile';

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string
    {
        return 'My profile';
    }
}

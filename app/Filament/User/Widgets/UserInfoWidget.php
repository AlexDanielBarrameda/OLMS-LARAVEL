<?php

namespace App\Filament\User\Widgets;

use App\Models\Transaction;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class UserInfoWidget extends Widget
{
    protected static bool $isLazy = false;

    // FIX: Removed the "static" keyword from this line!
    protected string $view = 'filament.user.widgets.user-info-widget';
    
    protected int | string | array $columnSpan = 'full';
    
    protected static ?int $sort = 1; // Puts it at the very top

    protected function getViewData(): array
    {
        return [
            'user' => Auth::user(),
            'currentlyBorrowed' => Transaction::where('user_id', Auth::id())
                ->where('fine_status', '!=', 'Returned')
                ->count(),
        ];
    }
}
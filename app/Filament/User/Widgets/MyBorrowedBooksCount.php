<?php

namespace App\Filament\User\Widgets;

use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget; // Use the standard base class
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth; // Import the Auth facade

class MyBorrowedBooksCount extends BaseWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        return [
            Stat::make('My Borrowed Books', Transaction::where('user_id', Auth::id())->count())
                ->description('Books currently with you')
                ->descriptionIcon('heroicon-m-bookmark')
                ->color('warning'),
        ];
    }
}
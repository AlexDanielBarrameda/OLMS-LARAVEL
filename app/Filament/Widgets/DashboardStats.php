<?php

namespace App\Filament\Widgets;

use App\Models\Book;
use App\Models\User;
use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStats extends BaseWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        return [
            Stat::make('Total Books', Book::sum('stock'))
                ->description('Total inventory across all titles')
                ->descriptionIcon('heroicon-m-book-open')
                ->color('success'),
                
            Stat::make('Borrowed Books', Book::where('status', 'Borrowed')->count())
                ->description('Books currently out')
                ->descriptionIcon('heroicon-m-arrow-right-on-rectangle')
                ->color('warning'),
                
            Stat::make('Active Users', User::where('status', 'Active')->count())
                ->description('Students and faculty')
                ->descriptionIcon('heroicon-m-users')
                ->color('success'),
                
            Stat::make('Pending Fines', Transaction::where('fine_status', 'Pending')->count())
                ->description('Transactions needing attention')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color('danger'),
        ];
    }
}
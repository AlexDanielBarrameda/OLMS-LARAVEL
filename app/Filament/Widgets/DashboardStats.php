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
        $titles = Book::count();
        $borrowed = Transaction::active()->count();
        $overdue = Transaction::overdue()->count();

        return [
            Stat::make('Total Books', number_format(Book::sum('stock')))
                ->description('Copies on the shelf · '.number_format($titles).' '.str('title')->plural($titles))
                ->descriptionIcon('heroicon-m-book-open')
                ->color('success'),

            // Approved loans that haven't been returned yet
            Stat::make('Borrowed Books', number_format($borrowed))
                ->description('Books currently out')
                ->descriptionIcon('heroicon-m-arrow-right-on-rectangle')
                ->color('warning'),

            Stat::make('Active Users', number_format(User::whereIn('role', ['student', 'user'])->whereIn('status', ['active', 'Active'])->count()))
                ->description('Students and faculty')
                ->descriptionIcon('heroicon-m-users')
                ->color('success'),

            // Overdue loans are what accrue fines
            Stat::make('Pending Fines', number_format($overdue))
                ->description($overdue ? 'Overdue loans needing attention' : 'Nothing overdue')
                ->descriptionIcon($overdue ? 'heroicon-m-exclamation-circle' : 'heroicon-m-check-circle')
                ->color($overdue ? 'danger' : 'success'),
        ];
    }
}

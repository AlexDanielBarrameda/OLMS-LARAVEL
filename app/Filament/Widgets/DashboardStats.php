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
        $borrowed = Transaction::checkedOut()->count();
        $overdue = Transaction::overdue()->count();
        $toVerify = Transaction::awaitingFineReview()->count();

        return [
            Stat::make('Total Books', number_format(Book::sum('stock')))
                ->description('Copies on the shelf · '.number_format($titles).' '.str('title')->plural($titles))
                ->descriptionIcon('heroicon-m-book-open')
                ->color('gray'),

            // Books still with students (on loan, or waiting for their fine payment to be verified)
            Stat::make('Borrowed Books', number_format($borrowed))
                ->description('Books currently out')
                ->descriptionIcon('heroicon-m-arrow-right-on-rectangle')
                ->color('gray'),

            Stat::make('Active Users', number_format(User::whereIn('role', ['student', 'user'])->whereIn('status', ['active', 'Active'])->count()))
                ->description('Students and faculty')
                ->descriptionIcon('heroicon-m-users')
                ->color('gray'),

            // Overdue loans are what accrue fines
            Stat::make('Pending Fines', number_format($overdue))
                ->description(match (true) {
                    $toVerify > 0 => ($overdue ? 'Overdue loans · ' : '').$toVerify.' '.str('payment')->plural($toVerify).' to verify',
                    $overdue > 0 => 'Overdue loans needing attention',
                    default => 'Nothing overdue',
                })
                ->descriptionIcon($overdue || $toVerify ? 'heroicon-m-exclamation-circle' : 'heroicon-m-check-circle')
                ->color($overdue || $toVerify ? 'danger' : 'success'),
        ];
    }
}

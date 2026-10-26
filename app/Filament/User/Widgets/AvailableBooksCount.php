<?php

namespace App\Filament\User\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AvailableBooksCount extends StatsOverviewWidget
{
    protected ?string $pollingInterval = null;

    protected function getStats(): array
    {
        return [
        Stat::make('Available Books', \App\Models\Book::sum('stock'))
            ->description('In-stock titles you can borrow')
            ->descriptionIcon('heroicon-m-book-open')
            ->color('success'),
        ];
    }
}

<?php

namespace App\Filament\User\Widgets;

use App\Models\Transaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class ProfileStatsWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 2; // Puts it in the middle

    protected function getStats(): array
    {
        // Calculate all three stats in a single query
        $stats = Transaction::where('user_id', Auth::id())
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COALESCE(SUM(fine_status != 'Returned'), 0) as current")
            ->selectRaw("COALESCE(SUM(fine_status != 'Returned' AND due_date < ?), 0) as overdue", [now()])
            ->first();

        return [
            Stat::make('Total Books Borrowed', (int) $stats->total),
            Stat::make('Currently Borrowed', (int) $stats->current),
            Stat::make('Overdue Books', (int) $stats->overdue),
        ];
    }
}
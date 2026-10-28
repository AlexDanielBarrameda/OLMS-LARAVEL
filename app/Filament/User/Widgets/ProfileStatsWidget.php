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
        // Only loans count (requests that were never approved don't)
        $stats = Transaction::where('user_id', Auth::id())
            ->lent()
            ->toBase()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("COALESCE(SUM(CASE WHEN status IN ('approved', 'pending_fine_verification') AND return_date IS NULL THEN 1 ELSE 0 END), 0) as current")
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'approved' AND return_date IS NULL AND due_date < ? THEN 1 ELSE 0 END), 0) as overdue", [today()->toDateString()])
            ->first();

        return [
            Stat::make('Total Books Borrowed', (int) $stats->total),
            Stat::make('Currently Borrowed', (int) $stats->current),
            Stat::make('Overdue Books', (int) $stats->overdue),
        ];
    }
}
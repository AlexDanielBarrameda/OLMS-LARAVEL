<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Illuminate\Support\Carbon;
use Filament\Widgets\ChartWidget;

class MonthlyBorrowingChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Monthly Borrowing Trends';

    protected static ?int $sort = 3; // Sits next to the Pie Chart (sort 2)

    // Set to half-width
    protected int | string | array $columnSpan = '1';

    protected function getData(): array
    {
        $start = now()->startOfMonth()->subMonths(5);

        // Count per month in PHP (works on any database) and keep empty months so the axis doesn't skip
        $counts = Transaction::query()
            ->lent() // requests that were never approved aren't borrows
            ->whereDate('borrow_date', '>=', $start->toDateString())
            ->pluck('borrow_date')
            ->countBy(fn ($date) => Carbon::parse($date)->format('Y-m'));

        $months = collect(range(0, 5))->map(fn (int $i) => $start->copy()->addMonths($i));

        return [
            'datasets' => [
                [
                    'label' => 'Books Borrowed',
                    'data' => $months->map(fn (Carbon $month) => $counts->get($month->format('Y-m'), 0))->all(),
                    'backgroundColor' => '#22c55e',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $months->map(fn (Carbon $month) => $month->format('F'))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'suggestedMax' => 10, // Forces the top number to be at least 10
                    'ticks' => [
                        'precision' => 0, // Removes decimals, allows clean auto-scaling for large numbers
                    ],
                ],
            ],
        ];
    }
}
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

    // Half width, next to Books by Category (sort 2)
    protected static ?int $sort = 3;

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
                    'backgroundColor' => BooksByCategoryChart::BAR_COLOR,
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
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'suggestedMax' => 10, // A near-empty month still gets a sensible scale
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }
}
<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
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
        // We use MIN(borrow_date) to allow sorting while using GROUP BY
        $data = Transaction::selectRaw('MONTHNAME(borrow_date) as month, count(*) as count, MIN(borrow_date) as sort_date')
            ->where('borrow_date', '>=', now()->subMonths(6))
            ->groupBy('month')
            ->orderBy('sort_date')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Books Borrowed',
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => '#22c55e', 
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $data->pluck('month')->toArray(),
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
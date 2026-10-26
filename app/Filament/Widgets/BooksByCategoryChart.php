<?php

namespace App\Filament\Widgets;

use App\Models\Book;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class BooksByCategoryChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    // Removed the "static" keyword here to fix the fatal error
    protected ?string $heading = 'Books by Category';

    // Set to half-width
    protected int | string | array $columnSpan = 1;
    // Optional: Make the chart a bit smaller so it doesn't take up the whole screen
    protected static ?int $sort = 2; 

    protected function getData(): array
    {
        // Query the database to group books by category and count them
        $data = Book::select('category', DB::raw('count(*) as count'))
            ->groupBy('category')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Books',
                    'data' => $data->pluck('count')->toArray(),
                    'backgroundColor' => [
                        '#166534', // Dark Green
                        '#22c55e', // Light Green
                        '#86efac', // Pale Green
                        '#fcd34d', // Yellow/Gold
                        '#fb923c', // Orange
                        '#94a3b8', // Gray for 'Other'
                    ],
                ],
            ],
            'labels' => $data->pluck('category')->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
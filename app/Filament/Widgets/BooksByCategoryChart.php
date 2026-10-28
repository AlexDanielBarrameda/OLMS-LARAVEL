<?php

namespace App\Filament\Widgets;

use App\Models\Book;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

// Largest categories as one-tone horizontal bars (a pie with dozens of categories was unreadable)
class BooksByCategoryChart extends ChartWidget
{
    // Categories shown by name; the rest are summed into "Other"
    protected const TOP_CATEGORIES = 8;

    // emerald-500: the brand green, and reads on both the light and dark card backgrounds
    public const BAR_COLOR = '#10b981';

    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Books by Category';

    protected int | string | array $columnSpan = 1;

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $counts = Book::query()
            ->select('category', DB::raw('count(*) as books'))
            ->groupBy('category')
            ->orderByDesc('books')
            ->pluck('books', 'category');

        $top = $counts->take(static::TOP_CATEGORIES);
        $other = $counts->skip(static::TOP_CATEGORIES)->sum();

        if ($other > 0) {
            $top->put('Other', $other);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Books',
                    'data' => $top->values()->all(),
                    'backgroundColor' => static::BAR_COLOR,
                    'borderRadius' => 4,
                    'maxBarThickness' => 18,
                ],
            ],
            'labels' => $top->keys()->map(fn ($category) => filled($category) ? $category : 'Uncategorized')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
                'y' => [
                    'grid' => ['display' => false],
                ],
            ],
        ];
    }
}

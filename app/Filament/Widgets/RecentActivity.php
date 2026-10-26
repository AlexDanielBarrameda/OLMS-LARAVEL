<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentActivity extends BaseWidget
{
    protected static bool $isLazy = false;

    // Make it sit below the charts
    protected static ?int $sort = 4;

    // Make it take up the full width of the dashboard
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // We use latest() to show the most recent borrows first
                Transaction::query()->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable(),
                Tables\Columns\TextColumn::make('book.title')
                    ->label('Book')
                    ->searchable(),
                Tables\Columns\TextColumn::make('borrow_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('fine_status')
                    ->label('Action') // Matches your Figma 'Action' column
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'None' => 'success',
                        'Pending' => 'danger',
                        'Paid' => 'gray',
                        default => 'primary',
                    }),
            ]);
    }
}
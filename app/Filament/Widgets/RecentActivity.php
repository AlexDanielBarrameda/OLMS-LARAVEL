<?php

namespace App\Filament\Widgets;

use App\Models\Transaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentActivity extends BaseWidget
{
    // Make it sit below the charts
    protected static ?int $sort = 4;

    // Make it take up the full width of the dashboard
    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Most recently changed first, so approvals and returns surface too, not just new borrows
                Transaction::query()->latest('updated_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('User')
                    ->searchable(),
                Tables\Columns\TextColumn::make('book.title')
                    ->label('Book')
                    ->searchable(),
                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->state(fn (Transaction $record): string => $record->isOverdue() ? 'Overdue' : $record->status->activityLabel())
                    ->color(fn (Transaction $record): string => $record->isOverdue() ? 'danger' : $record->status->getColor())
                    ->icon(fn (Transaction $record): string => $record->isOverdue() ? 'heroicon-m-exclamation-triangle' : $record->status->getIcon()),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('When')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
            ]);
    }
}

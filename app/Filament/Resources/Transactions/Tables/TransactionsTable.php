<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Models\Transaction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Database\Eloquent\Builder;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Overdue loans first (most overdue on top), then other active loans by due date, then returned ones
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw(
                    "CASE
                        WHEN transactions.fine_status <> 'Returned' AND transactions.return_date IS NULL AND transactions.due_date < ? THEN 0
                        WHEN transactions.fine_status <> 'Returned' AND transactions.return_date IS NULL THEN 1
                        ELSE 2
                    END",
                    [today()->toDateString()]
                )
                ->orderBy('transactions.due_date'))
            ->recordClasses(fn (Transaction $record) => $record->isOverdue() ? 'bg-transaction-overdue' : null)
            ->columns([
                TextColumn::make('user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('book.title')
                    ->label('Book')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('loan_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Transaction $record): string => match (true) {
                        ! $record->isActive() => 'Returned',
                        default => $record->dueLabel() ?? 'On loan',
                    })
                    ->color(fn (Transaction $record): string => match (true) {
                        ! $record->isActive() => 'gray',
                        $record->isOverdue() => 'danger',
                        $record->dueLabel() !== null => 'warning',
                        default => 'success',
                    })
                    ->icon(fn (Transaction $record): ?string => $record->isOverdue() ? 'heroicon-m-exclamation-triangle' : null),
                TextColumn::make('borrow_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->color(fn (Transaction $record): ?string => $record->isOverdue() ? 'danger' : null),
                TextColumn::make('return_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('fine_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'None' => 'success',
                        'Pending' => 'danger',
                        'Paid' => 'gray',
                        default => 'primary',
                    }),
            ])
            ->filters([
                Filter::make('overdue')
                    ->label('Overdue only')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->overdue()),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

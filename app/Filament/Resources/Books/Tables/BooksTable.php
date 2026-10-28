<?php

namespace App\Filament\Resources\Books\Tables;

use App\Models\Book;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;

class BooksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // How many times each title has been lent (approved loans, current or returned), and whether it has any
            // transactions at all (books with history can't be deleted; loaded here so the Delete check doesn't query per row)
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount([
                'transactions as times_lent' => fn (Builder $loans): Builder => $loans->lent(),
                'transactions',
            ]))
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('author')
                    ->searchable(),
                TextColumn::make('isbn')
                    ->label('ISBN')
                    ->searchable()
                    ->placeholder('Not set')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('category')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Available' => 'success',
                        'Borrowed' => 'gray',
                        'Damaged' => 'danger',
                        Book::STATUS_ON_ORDER => 'info',
                        default => 'primary',
                    }),
                TextColumn::make('stock')
                    ->numeric()
                    ->sortable()
                    ->weight(fn (int $state): ?string => $state <= Book::LOW_STOCK ? 'semibold' : null)
                    ->color(fn (int $state): ?string => match (true) {
                        $state <= 0 => 'danger',
                        $state <= Book::LOW_STOCK => 'warning',
                        default => null,
                    }),
                TextColumn::make('times_lent')
                    ->label('Times lent')
                    ->sortable()
                    ->badge(fn (int $state): bool => $state >= Book::POPULAR_AFTER)
                    ->formatStateUsing(fn (int $state): string => $state >= Book::POPULAR_AFTER ? "Popular · {$state}" : (string) $state)
                    ->color(fn (int $state): ?string => $state >= Book::POPULAR_AFTER ? 'warning' : 'gray')
                    ->icon(fn (int $state): ?string => $state >= Book::POPULAR_AFTER ? 'heroicon-m-fire' : null),
            ])
            ->filters([
                Filter::make('low_stock')
                    ->label('Low stock ('.Book::LOW_STOCK.' or fewer)')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->lowStock()),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    // Checks each book, so ones with borrowing history are skipped (and reported) instead of failing the whole delete
                    DeleteBulkAction::make()->authorizeIndividualRecords('delete'),
                ]),
            ]);
    }
}
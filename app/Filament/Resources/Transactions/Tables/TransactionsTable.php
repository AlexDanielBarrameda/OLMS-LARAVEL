<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Enums\TransactionStatus;
use App\Models\Book;
use App\Models\Transaction;
use App\Services\LibraryNotifications;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Requests waiting for review first, then overdue loans (most overdue on top), other loans by due date, then closed ones
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw(
                    "CASE
                        WHEN transactions.status = 'pending' THEN 0
                        WHEN transactions.status = 'approved' AND transactions.return_date IS NULL AND transactions.due_date < ? THEN 1
                        WHEN transactions.status = 'approved' AND transactions.return_date IS NULL THEN 2
                        ELSE 3
                    END",
                    [today()->toDateString()]
                )
                ->orderBy('transactions.due_date'))
            ->recordClasses(fn (Transaction $record) => match (true) {
                $record->isPending() => 'bg-transaction-pending',
                $record->isOverdue() => 'bg-transaction-overdue',
                default => null,
            })
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
                    ->state(fn (Transaction $record): string => $record->isActive()
                        ? ($record->dueLabel() ?? TransactionStatus::Approved->getLabel())
                        : $record->status->getLabel())
                    ->color(fn (Transaction $record): string => match (true) {
                        $record->isOverdue() => 'danger',
                        $record->dueLabel() !== null => 'warning',
                        default => $record->status->getColor(),
                    })
                    ->icon(fn (Transaction $record): string => $record->isOverdue()
                        ? 'heroicon-m-exclamation-triangle'
                        : $record->status->getIcon()),
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
                Filter::make('pending')
                    ->label('Pending requests')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->pending()),
                Filter::make('overdue')
                    ->label('Overdue only')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->overdue()),
                SelectFilter::make('status')
                    ->options(TransactionStatus::class),
            ])
            ->actions([
                static::approveAction(),
                static::rejectAction(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-m-check')
            ->color('success')
            ->visible(fn (Transaction $record): bool => $record->isPending())
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-check-circle')
            ->modalHeading(fn (Transaction $record): string => "Lend \"{$record->book->title}\" to {$record->user->name}?")
            ->modalDescription(fn (Transaction $record): string => $record->book->stock.' '.str('copy')->plural($record->book->stock)
                .' on the shelf. It will be due back on '.today()->addDays(Transaction::LOAN_DAYS)->format('M j').'.')
            ->modalSubmitActionLabel('Approve')
            ->action(function (Transaction $record, Action $action): void {
                // Lock the request and the book so two librarians can't lend the last copy twice
                $error = DB::transaction(function () use ($record): ?string {
                    $loan = Transaction::lockForUpdate()->find($record->id);
                    $book = Book::lockForUpdate()->find($record->book_id);

                    if (! $loan?->isPending()) {
                        return 'This request has already been handled.';
                    }

                    if (! $book || $book->stock <= 0) {
                        return "\"{$record->book->title}\" is out of stock.";
                    }

                    // Starts the loan period and takes a copy off the shelf
                    $loan->approve();

                    return null;
                });

                if ($error) {
                    Notification::make()->title("Can't approve this request")->body($error)->danger()->send();

                    $action->cancel();
                }

                app(LibraryNotifications::class)->borrowDecided($record->refresh());

                Notification::make()->title('Request approved')->success()->send();
            });
    }

    protected static function rejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon('heroicon-m-x-mark')
            ->color('danger')
            ->visible(fn (Transaction $record): bool => $record->isPending())
            ->modalIcon('heroicon-o-x-circle')
            ->modalIconColor('danger')
            ->modalHeading(fn (Transaction $record): string => "Reject {$record->user->name}'s request?")
            ->modalDescription(fn (Transaction $record): string => "\"{$record->book->title}\" won't be lent. The student is notified either way.")
            ->modalWidth('md')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason (optional)')
                    ->placeholder('e.g. All copies are reserved for a class this week.')
                    ->rows(3)
                    ->maxLength(500),
            ])
            ->modalSubmitActionLabel('Reject request')
            ->action(function (Transaction $record, array $data): void {
                $record->update(['status' => TransactionStatus::Rejected]);

                app(LibraryNotifications::class)->borrowDecided($record, $data['reason'] ?? null);

                Notification::make()->title('Request rejected')->success()->send();
            });
    }
}

<?php

namespace App\Filament\User\Widgets;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\LibraryNotifications;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Actions\Action; // Corrected import for Filament v4
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On; // <-- Added for event listening
use Livewire\Component; // <-- Added to dispatch events

class MyBorrowedBooksWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'My Books';

    // 1. Listen for changes (like when a book is borrowed)
    #[On('update-book-tables')]
    public function refreshBorrowedBooks(): void {}

    // "Nov 7" this year, "Jan 3, 2027" otherwise — short enough not to wrap in narrow cards
    protected static function shortDate($state): string
    {
        $date = Carbon::parse($state);

        return $date->format($date->isCurrentYear() ? 'M j' : 'M j, Y');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Transaction::query()
                    ->where('user_id', Auth::id())
                    ->open()
            )
            // Loans first, most urgent on top; requests awaiting approval after them
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('CASE WHEN status = ? THEN 1 ELSE 0 END', [TransactionStatus::Pending->value])
                ->orderBy('due_date'))
            ->recordClasses(fn (Transaction $record) => match (true) {
                $record->isPending() => 'bg-book-pending',
                $record->daysUntilDue() < 0 => 'bg-book-overdue',
                $record->daysUntilDue() <= Transaction::DUE_SOON_DAYS => 'bg-book-due-soon',
                default => null,
            })
            ->extraAttributes(['class' => 'bg-books'])
            ->contentGrid([
                'default' => 2,
                'sm' => 3,
                'lg' => 4,
                'xl' => 5,
            ])
            ->columns([
                Stack::make([
                    Tables\Columns\ImageColumn::make('cover')
                        ->state(fn (Transaction $record) => $record->book->coverUrl())
                        ->imageWidth('100%')
                        ->imageHeight('auto')
                        ->extraImgAttributes(fn (Transaction $record) => [
                            'alt' => e($record->book->title),
                            'loading' => 'lazy',
                            'class' => 'bg-book-cover',
                            'onerror' => "this.onerror=null;this.src='{$record->book->fallbackCoverUrl()}'",
                        ]),
                    Tables\Columns\TextColumn::make('book.title')
                        ->weight('bold')
                        ->size('lg'),
                    
                    Tables\Columns\TextColumn::make('book.author')
                        ->color('gray'),
                    
                    Tables\Columns\TextColumn::make('borrow_date')
                        ->formatStateUsing(fn ($state, Transaction $record) => ($record->isPending() ? 'Requested ' : 'Borrowed ').static::shortDate(
                            $record->isPending() ? $record->created_at : $state
                        ))
                        ->icon('heroicon-o-calendar'),

                    // Requests don't have a real due date until they're approved
                    Tables\Columns\TextColumn::make('due_date')
                        ->state(fn (Transaction $record) => $record->isPending() ? null : $record->due_date)
                        ->formatStateUsing(fn ($state) => 'Due '.static::shortDate($state))
                        ->icon('heroicon-o-calendar-days')
                        ->color(fn (Transaction $record) => $record->isOverdue() ? 'danger' : null),

                    Tables\Columns\TextColumn::make('due_status')
                        ->state(fn (Transaction $record) => $record->isPending()
                            ? TransactionStatus::Pending->getLabel()
                            : $record->dueLabel())
                        ->badge()
                        ->color(fn (Transaction $record) => match (true) {
                            $record->isPending() => 'info',
                            $record->isOverdue() => 'danger',
                            default => 'warning',
                        })
                        ->icon(fn (Transaction $record) => match (true) {
                            $record->isPending() => 'heroicon-m-ellipsis-horizontal-circle',
                            $record->isOverdue() => 'heroicon-m-exclamation-triangle',
                            default => 'heroicon-m-clock',
                        }),
                ])->space(2),
            ])
            ->actions([
                Action::make('return')
                    ->label('Return Book')
                    ->button()
                    ->color('success')
                    ->visible(fn (Transaction $record) => $record->isActive())
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Return')
                    ->modalDescription('Are you sure you want to return this book?')
                    // 2. Inject $livewire into the closure
                    ->action(function (Transaction $record, Component $livewire) {
                        // Marking it returned sets the return date and puts the copy back on the shelf
                        $record->update(['status' => TransactionStatus::Returned]);

                        Notification::make()
                            ->title('Book Returned Successfully!')
                            ->success()
                            ->send();

                        // 3. Shout out to the page that the tables need to refresh!
                        $livewire->dispatch('update-book-tables');
                    }),

                Action::make('cancelRequest')
                    ->label('Cancel request')
                    ->button()
                    ->color('gray')
                    ->visible(fn (Transaction $record) => $record->isPending())
                    ->requiresConfirmation()
                    ->modalHeading(fn (Transaction $record) => "Cancel your request for \"{$record->book->title}\"?")
                    ->modalSubmitActionLabel('Cancel request')
                    ->modalCancelActionLabel('Keep it')
                    ->action(function (Transaction $record, Component $livewire) {
                        // Still pending, so no stock was taken
                        $record->delete();

                        app(LibraryNotifications::class)->borrowCancelled($record);

                        Notification::make()->title('Request cancelled')->success()->send();

                        $livewire->dispatch('update-book-tables');
                    }),
            ]);
    }
}
<?php

namespace App\Filament\User\Widgets;

use App\Models\Book;
use App\Models\Transaction;
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
    protected static ?string $heading = 'My Borrowed Books';

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
                    ->active()
            )
            // Most urgent first
            ->defaultSort('due_date')
            ->recordClasses(fn (Transaction $record) => match (true) {
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
                        ->formatStateUsing(fn ($state) => 'Borrowed '.static::shortDate($state))
                        ->icon('heroicon-o-calendar'),

                    Tables\Columns\TextColumn::make('due_date')
                        ->formatStateUsing(fn ($state) => 'Due '.static::shortDate($state))
                        ->icon('heroicon-o-calendar-days')
                        ->color(fn (Transaction $record) => $record->daysUntilDue() < 0 ? 'danger' : null),

                    Tables\Columns\TextColumn::make('due_status')
                        ->state(fn (Transaction $record) => $record->dueLabel())
                        ->badge()
                        ->color(fn (Transaction $record) => $record->daysUntilDue() < 0 ? 'danger' : 'warning')
                        ->icon(fn (Transaction $record) => $record->daysUntilDue() < 0
                            ? 'heroicon-m-exclamation-triangle'
                            : 'heroicon-m-clock'),
                ])->space(2),
            ])
            ->actions([
                Action::make('return')
                    ->label('Return Book')
                    ->button()
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Confirm Return')
                    ->modalDescription('Are you sure you want to return this book?')
                    // 2. Inject $livewire into the closure
                    ->action(function (Transaction $record, Component $livewire) {
                        $record->update([
                            'fine_status' => 'Returned',
                            'return_date' => today(),
                        ]);

                        Book::whereKey($record->book_id)->increment('stock');

                        Notification::make()
                            ->title('Book Returned Successfully!')
                            ->success()
                            ->send();
                            
                        // 3. Shout out to the page that the tables need to refresh!
                        $livewire->dispatch('update-book-tables');
                    }),
            ]);
    }
}
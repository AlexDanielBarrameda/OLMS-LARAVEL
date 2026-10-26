<?php

namespace App\Filament\User\Widgets;

use App\Models\Book;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Actions\Action; // Corrected import for Filament v4
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On; // <-- Added for event listening
use Livewire\Component; // <-- Added to dispatch events

class AvailableBooksWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Available Books';

    // 1. Listen for changes (like when a book is returned)
    #[On('update-book-tables')]
    public function refreshAvailableBooks(): void {}

    public function table(Table $table): Table
    {
        return $table
            ->query(Book::query())
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
                        ->state(fn (Book $record) => $record->coverUrl())
                        ->imageWidth('100%')
                        ->imageHeight('auto')
                        ->extraImgAttributes(fn (Book $record) => [
                            'alt' => e($record->title),
                            'loading' => 'lazy',
                            'class' => 'bg-book-cover',
                            'onerror' => "this.onerror=null;this.src='{$record->fallbackCoverUrl()}'",
                        ]),
                    Tables\Columns\TextColumn::make('title')->weight('bold')->size('lg')->searchable(),
                    Tables\Columns\TextColumn::make('author')->color('gray')->searchable(),
                    Tables\Columns\TextColumn::make('category')
                        ->formatStateUsing(fn ($state) => "Category: {$state}"),
                    Tables\Columns\TextColumn::make('stock')
                        ->formatStateUsing(fn ($state) => "{$state} left")
                        ->badge()
                        ->color(fn ($state) => $state > 0 ? 'success' : 'danger'),
                ])->space(2),
            ])
            ->actions([
                Action::make('borrow')
                    ->label('Borrow')
                    ->button()
                    ->color('success')
                    ->requiresConfirmation()
                    ->disabled(fn (Book $record) => $record->stock <= 0)
                    // 2. Inject $livewire into the closure
                    ->action(function (Book $record, Component $livewire, Action $action) {
                        // Lock the user and the book so double clicks can't slip past the rules or oversell stock
                        $blockedReason = DB::transaction(function () use ($record): ?string {
                            $user = User::lockForUpdate()->findOrFail(Auth::id());
                            $book = Book::lockForUpdate()->findOrFail($record->id);

                            if ($book->stock <= 0) {
                                return "\"{$book->title}\" is out of stock.";
                            }

                            if ($reason = $user->borrowingBlockedReason($book)) {
                                return $reason;
                            }

                            Transaction::create([
                                'user_id' => $user->id,
                                'book_id' => $book->id,
                                'borrow_date' => now(),
                                'due_date' => now()->addDays(14),
                                'fine_status' => 'None',
                            ]);
                            $book->decrement('stock');

                            return null;
                        });

                        if ($blockedReason) {
                            Notification::make()
                                ->title("Can't borrow this book")
                                ->body($blockedReason)
                                ->danger()
                                ->send();

                            $action->cancel();
                        }

                        Notification::make()->title('Book Borrowed!')->success()->send();
                        
                        // 3. Shout out to the page that the tables need to refresh!
                        $livewire->dispatch('update-book-tables');
                    }),
            ]);
    }
}
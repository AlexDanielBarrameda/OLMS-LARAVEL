<?php

namespace App\Filament\User\Widgets;

use App\Enums\TransactionStatus;
use App\Models\Book;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LibraryNotifications;
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

    // book_id => status of this user's open requests/loans, loaded once per render instead of per card
    protected ?array $openStatuses = null;

    // 1. Listen for changes (like when a book is returned)
    #[On('update-book-tables')]
    public function refreshAvailableBooks(): void
    {
        $this->openStatuses = null;
    }

    protected function openStatusFor(Book $book): ?TransactionStatus
    {
        $this->openStatuses ??= Transaction::query()
            ->where('user_id', Auth::id())
            ->open()
            ->get(['book_id', 'status'])
            ->mapWithKeys(fn (Transaction $loan) => [$loan->book_id => $loan->status])
            ->all();

        return $this->openStatuses[$book->id] ?? null;
    }

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
                    ->label(fn (Book $record) => match ($this->openStatusFor($record)) {
                        TransactionStatus::Pending => 'Requested',
                        TransactionStatus::Approved => 'Borrowed',
                        default => 'Request to Borrow',
                    })
                    ->button()
                    ->color('success')
                    ->disabled(fn (Book $record) => $record->stock <= 0 || $this->openStatusFor($record) !== null)
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-book-open')
                    ->modalHeading(fn (Book $record) => "Request \"{$record->title}\"?")
                    ->modalDescription('A librarian will review your request. You\'ll get a notification when it\'s approved.')
                    ->modalSubmitActionLabel('Send request')
                    // 2. Inject $livewire into the closure
                    ->action(function (Book $record, Component $livewire, Action $action) {
                        // Lock the user and the book so double clicks can't slip past the rules
                        $result = DB::transaction(function () use ($record): Transaction|string {
                            $user = User::lockForUpdate()->findOrFail(Auth::id());
                            $book = Book::lockForUpdate()->findOrFail($record->id);

                            if ($book->stock <= 0) {
                                return "\"{$book->title}\" is out of stock.";
                            }

                            if ($reason = $user->borrowingBlockedReason($book)) {
                                return $reason;
                            }

                            // Stock is only taken when a librarian approves; dates are reset then too
                            return Transaction::create([
                                'user_id' => $user->id,
                                'book_id' => $book->id,
                                'status' => TransactionStatus::Pending,
                                'borrow_date' => today()->toDateString(),
                                'due_date' => today()->addDays(Transaction::LOAN_DAYS)->toDateString(),
                                'fine_status' => 'None',
                            ]);
                        });

                        if (is_string($result)) {
                            Notification::make()
                                ->title("Can't request this book")
                                ->body($result)
                                ->danger()
                                ->send();

                            $action->cancel();
                        }

                        app(LibraryNotifications::class)->borrowRequested($result->load('user', 'book'));

                        Notification::make()
                            ->title('Request sent')
                            ->body('We\'ll let you know when a librarian approves it.')
                            ->success()
                            ->send();

                        $this->openStatuses = null;

                        // 3. Shout out to the page that the tables need to refresh!
                        $livewire->dispatch('update-book-tables');
                    }),
            ]);
    }
}
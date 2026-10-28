<?php

namespace App\Filament\User\Widgets;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Services\LibraryNotifications;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\View;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class MyBorrowedBooksWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'My Books';

    // Refresh when another widget changes a loan (a new borrow request)
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
                // An unconfirmed return past its due date still shows as overdue
                $record->isAwaitingFineReview(), $record->isAwaitingReturn() && ! $record->isOverdue() => null,
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
                            // Decorative: the title is printed right below, so screen readers shouldn't hear it twice
                            'alt' => '',
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
                        ->state(fn (Transaction $record) => match (true) {
                            $record->isPending() => TransactionStatus::Pending->getLabel().($record->duration_days ? ' · '.$record->duration_days.' days' : ''),
                            $record->isAwaitingFineReview() => 'Fine under review · '.Transaction::peso($record->fineDue()),
                            $record->isAwaitingReturn() => 'Awaiting check-in'.($record->isOverdue() ? ' · overdue' : ''),
                            default => $record->dueLabel(),
                        })
                        ->badge()
                        ->color(fn (Transaction $record) => match (true) {
                            // The student has already paid: show the review as in progress, not as an alarm
                            $record->isAwaitingFineReview() => 'info',
                            $record->isOverdue() => 'danger',
                            $record->isPending(), $record->isAwaitingReturn() => 'info',
                            default => 'warning',
                        })
                        ->icon(fn (Transaction $record) => match (true) {
                            $record->isPending() => 'heroicon-m-ellipsis-horizontal-circle',
                            $record->isAwaitingFineReview() => 'heroicon-m-receipt-percent',
                            $record->isAwaitingReturn() => 'heroicon-m-arrow-uturn-left',
                            $record->isOverdue() => 'heroicon-m-exclamation-triangle',
                            default => 'heroicon-m-clock',
                        }),
                ])->space(2),
            ])
            ->actions([
                Action::make('return')
                    ->label('Return Book')
                    ->button()
                    ->color('primary')
                    // Overdue books go through "returnOverdue" (fine payment) instead
                    ->visible(fn (Transaction $record) => $record->isActive() && ! $record->isOverdue())
                    ->requiresConfirmation()
                    ->modalHeading('Return this book?')
                    ->modalDescription('Bring it to the library desk. It stays on your account until a librarian checks it in.')
                    ->modalSubmitActionLabel('Return book')
                    // Only staff put the copy back on the shelf: this just tells them it's coming
                    ->action(function (Transaction $record, Component $livewire, Action $action) {
                        $returned = DB::transaction(function () use ($record): ?Transaction {
                            $loan = Transaction::lockForUpdate()->find($record->id);

                            if (! $loan?->isActive() || $loan->isOverdue() || $loan->user_id !== Auth::id()) {
                                return null;
                            }

                            $loan->requestReturn();

                            return $loan;
                        });

                        if (! $returned) {
                            Notification::make()->title('This book can\'t be returned this way any more')->warning()->send();
                            $action->cancel();
                        }

                        app(LibraryNotifications::class)->returnRequested($returned->load('user', 'book'));

                        Notification::make()
                            ->title('Return requested')
                            ->body('Bring the book to the library desk. A librarian will check it in.')
                            ->success()
                            ->send();

                        $livewire->dispatch('update-book-tables');
                    }),

                // Overdue: pay the fine via GCash and upload the receipt; staff verify it and check the book in
                Action::make('returnOverdue')
                    ->label('Return Book')
                    ->button()
                    ->color('danger')
                    // Loans still with the student; an overdue return waiting for check-in is staff's call ("Verify Return" or "Not received")
                    ->visible(fn (Transaction $record) => $record->isActive() && $record->isOverdue())
                    ->modalIcon('heroicon-o-receipt-percent')
                    ->modalIconColor('danger')
                    ->modalHeading('Return overdue book')
                    ->modalDescription(fn (Transaction $record) => "\"{$record->book->title}\" is overdue, so there's a fine to pay before it can be returned.")
                    ->modalWidth('lg')
                    ->schema(fn (Transaction $record) => [
                        View::make('filament.user.payment')->viewData([
                            'tone' => 'danger',
                            'label' => 'Fine due',
                            'amount' => Transaction::peso($record->fineDue()),
                            'breakdown' => $record->daysOverdue().' '.str('day')->plural($record->daysOverdue()).' late × '.Transaction::peso(Transaction::FINE_PER_DAY).' per day',
                            'steps' => [
                                'Scan the QR with GCash (or save it and upload it in GCash) and pay <strong>'.e(Transaction::peso($record->fineDue())).'</strong>.',
                                'Upload a screenshot of your receipt below.',
                                'Bring the book to the library desk. A librarian will verify your payment and check it in.',
                            ],
                            'qrUrl' => asset('images/gcash-qr.png'),
                        ]),
                        FileUpload::make('receipt')
                            ->label('Payment receipt / screenshot')
                            ->helperText('A screenshot of the GCash confirmation, showing the amount and reference number. PNG or JPG, up to 5 MB.')
                            ->required()
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->maxSize(5120)
                            ->disk('receipts')
                            ->directory(now()->format('Y/m'))
                            ->visibility('private'),
                    ])
                    ->modalSubmitActionLabel('Submit payment')
                    ->action(function (Transaction $record, array $data, Component $livewire, Action $action) {
                        // Re-check on the server: it must still be this student's overdue loan
                        $loan = $record->fresh();

                        if (! $loan?->isActive() || ! $loan->isOverdue() || $loan->user_id !== Auth::id()) {
                            Notification::make()->title('This book no longer needs a fine payment')->warning()->send();
                            $action->cancel();
                        }

                        $loan->submitFinePayment($data['receipt']);

                        app(LibraryNotifications::class)->finePaymentSubmitted($loan->load('user', 'book'));

                        Notification::make()
                            ->title('Payment submitted')
                            ->body('Bring the book to the library desk. A librarian will verify your payment and check it in.')
                            ->success()
                            ->send();

                        $livewire->dispatch('update-book-tables');
                    }),

                Action::make('cancelRequest')
                    ->label('Cancel request')
                    ->button()
                    ->color('gray')
                    ->visible(fn (Transaction $record) => $record->isPending())
                    ->requiresConfirmation()
                    ->modalHeading(fn (Transaction $record) => "Cancel your request for \"{$record->book->title}\"?")
                    ->modalDescription(fn (Transaction $record) => LibraryNotifications::refundPromise($record))
                    ->modalSubmitActionLabel('Cancel request')
                    ->modalCancelActionLabel('Keep it')
                    ->action(function (Transaction $record, Component $livewire) {
                        // Still pending, so no stock was taken. Kept as "cancelled" (not deleted) so a paid fee is tracked until it's refunded.
                        $record->update(['status' => TransactionStatus::Cancelled]);

                        app(LibraryNotifications::class)->borrowCancelled($record);

                        Notification::make()->title('Request cancelled')->success()->send();

                        $livewire->dispatch('update-book-tables');
                    }),
            ]);
    }
}
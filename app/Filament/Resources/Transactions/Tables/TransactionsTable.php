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
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Things waiting on staff first (borrow requests, fine receipts), then overdue loans (most overdue on top), other loans by due date, then closed ones
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw(
                    "CASE
                        WHEN transactions.status IN ('pending', 'pending_fine_verification') THEN 0
                        WHEN transactions.status = 'approved' AND transactions.return_date IS NULL AND transactions.due_date < ? THEN 1
                        WHEN transactions.status = 'approved' AND transactions.return_date IS NULL THEN 2
                        ELSE 3
                    END",
                    [today()->toDateString()]
                )
                ->orderBy('transactions.due_date'))
            ->recordClasses(fn (Transaction $record) => match (true) {
                $record->isPending(), $record->isAwaitingFineReview() => 'bg-transaction-pending',
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
                    ->state(fn (Transaction $record): string => match (true) {
                        $record->isPending() && $record->duration_days => $record->status->getLabel().' · '.$record->duration_days.' days · '.Transaction::peso((float) $record->fee_amount),
                        $record->isActive() => $record->dueLabel() ?? TransactionStatus::Approved->getLabel(),
                        $record->isAwaitingFineReview() => $record->status->getLabel().' · '.Transaction::peso($record->fineDue()),
                        default => $record->status->getLabel(),
                    })
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
                TextColumn::make('refund_status')
                    ->label('Refund')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, Transaction $record): ?string => match ($state) {
                        Transaction::REFUND_PENDING => 'Refund pending · '.Transaction::peso((float) $record->fee_amount),
                        Transaction::REFUNDED => 'Refunded',
                        default => null,
                    })
                    ->description(fn (Transaction $record): ?string => $record->isRefunded() ? $record->refunded_at?->format('M j, Y') : null)
                    ->color(fn (?string $state): string => $state === Transaction::REFUNDED ? 'success' : 'warning')
                    ->icon(fn (?string $state): string => $state === Transaction::REFUNDED ? 'heroicon-m-check-circle' : 'heroicon-m-banknotes')
                    ->placeholder('—'),
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
                Filter::make('fine_review')
                    ->label('Fine payments to verify')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->awaitingFineReview()),
                Filter::make('refunds')
                    ->label('Refunds to process')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->refundPending()),
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
                static::verifyPaymentAction(),
                static::rejectPaymentAction(),
                static::processRefundAction(),
                static::viewRefundReceiptAction(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    // Send a rejected/cancelled request's fee back via GCash and keep the screenshot as proof
    protected static function processRefundAction(): Action
    {
        return Action::make('processRefund')
            ->label('Process Refund')
            ->icon('heroicon-m-banknotes')
            ->color('warning')
            ->visible(fn (Transaction $record): bool => $record->isRefundPending())
            ->modalIcon('heroicon-o-banknotes')
            ->modalIconColor('warning')
            ->modalHeading(fn (Transaction $record): string => 'Refund '.Transaction::peso((float) $record->fee_amount)." to {$record->user->name}")
            ->modalDescription(fn (Transaction $record): string => "Their request for \"{$record->book->title}\" was ".strtolower($record->status->getLabel()).'. Send the fee back via GCash, then upload your screenshot as proof.')
            ->modalContent(fn (Transaction $record) => view('filament.admin.refund', [
                'loan' => $record,
                'mode' => 'process',
                'imageUrl' => static::receiptUrl($record->borrow_receipt_path),
            ]))
            ->modalWidth('lg')
            ->schema([
                FileUpload::make('refund_receipt')
                    ->label('Refund GCash Screenshot')
                    ->helperText('The GCash confirmation of the money you sent back, showing the amount and reference number.')
                    ->required()
                    ->image()
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(5120)
                    ->disk('receipts')
                    ->directory('refunds/'.now()->format('Y/m'))
                    ->visibility('private'),
            ])
            ->modalSubmitActionLabel('Mark as refunded')
            ->action(function (Transaction $record, array $data, Action $action): void {
                $done = DB::transaction(function () use ($record, $data): bool {
                    $loan = Transaction::lockForUpdate()->find($record->id);

                    if (! $loan?->isRefundPending()) {
                        return false;
                    }

                    $loan->markRefunded($data['refund_receipt']);

                    return true;
                });

                if (! $done) {
                    // Someone else already recorded it; drop the duplicate screenshot
                    Storage::disk('receipts')->delete($data['refund_receipt']);
                    Notification::make()->title('This refund was already recorded')->warning()->send();
                    $action->cancel();
                }

                app(LibraryNotifications::class)->refundSent($record->refresh());

                Notification::make()->title('Refund recorded')->success()->send();
            });
    }

    // Audit: see the screenshot proving a refund was sent
    protected static function viewRefundReceiptAction(): Action
    {
        return Action::make('viewRefundReceipt')
            ->label('View Refund Receipt')
            ->icon('heroicon-m-eye')
            ->color('gray')
            ->visible(fn (Transaction $record): bool => $record->isRefunded() && filled($record->refund_receipt_path))
            ->modalIcon('heroicon-o-check-circle')
            ->modalIconColor('success')
            ->modalHeading(fn (Transaction $record): string => 'Refund to '.$record->user->name)
            ->modalDescription(fn (Transaction $record): string => "Fee for \"{$record->book->title}\"")
            ->modalContent(fn (Transaction $record) => view('filament.admin.refund', [
                'loan' => $record,
                'mode' => 'view',
                'imageUrl' => static::receiptUrl($record->refund_receipt_path),
            ]))
            ->modalWidth('lg')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close');
    }

    // Short-lived signed link to a file on the private receipts disk
    protected static function receiptUrl(?string $path): ?string
    {
        return $path ? Storage::disk('receipts')->temporaryUrl($path, now()->addMinutes(15)) : null;
    }

    // Look at the student's GCash receipt, then take the book back
    protected static function verifyPaymentAction(): Action
    {
        return Action::make('verifyPayment')
            ->label('Verify Payment & Return')
            ->icon('heroicon-m-check-badge')
            ->color('success')
            ->visible(fn (Transaction $record): bool => $record->isAwaitingFineReview())
            ->modalIcon('heroicon-o-receipt-percent')
            ->modalHeading(fn (Transaction $record): string => "Verify {$record->user->name}'s payment")
            ->modalDescription(fn (Transaction $record): string => "Check the receipt, then confirm once you have \"{$record->book->title}\" back.")
            ->modalContent(fn (Transaction $record) => view('filament.admin.fine-receipt', [
                'loan' => $record,
                'receiptUrl' => $record->payment_receipt_path
                    ? Storage::disk('receipts')->temporaryUrl($record->payment_receipt_path, now()->addMinutes(15))
                    : null,
            ]))
            ->modalWidth('lg')
            ->modalSubmitActionLabel('Confirm & check in')
            ->action(function (Transaction $record, Action $action): void {
                $error = DB::transaction(function () use ($record): ?string {
                    $loan = Transaction::lockForUpdate()->find($record->id);

                    if (! $loan?->isAwaitingFineReview()) {
                        return 'This payment has already been handled.';
                    }

                    // Marks the fine paid and returns the book (puts the copy back on the shelf)
                    $loan->confirmFineAndReturn();

                    return null;
                });

                if ($error) {
                    Notification::make()->title("Can't verify this payment")->body($error)->danger()->send();

                    $action->cancel();
                }

                app(LibraryNotifications::class)->finePaymentDecided($record->refresh(), accepted: true);

                Notification::make()->title('Payment verified and book returned')->success()->send();
            });
    }

    // The receipt doesn't check out: the loan goes back to overdue and the student is asked to pay again
    protected static function rejectPaymentAction(): Action
    {
        return Action::make('rejectPayment')
            ->label('Reject receipt')
            ->icon('heroicon-m-x-mark')
            ->color('danger')
            ->visible(fn (Transaction $record): bool => $record->isAwaitingFineReview())
            ->modalIcon('heroicon-o-x-circle')
            ->modalIconColor('danger')
            ->modalHeading('Reject this receipt?')
            ->modalDescription(fn (Transaction $record): string => "{$record->user->name} will be asked to pay again. The book stays checked out to them.")
            ->modalWidth('md')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->placeholder('e.g. The amount paid doesn\'t match the fine.')
                    ->required()
                    ->rows(3)
                    ->maxLength(500),
            ])
            ->modalSubmitActionLabel('Reject receipt')
            ->action(function (Transaction $record, array $data): void {
                $record->rejectFinePayment();

                app(LibraryNotifications::class)->finePaymentDecided($record, accepted: false, reason: $data['reason']);

                Notification::make()->title('Receipt rejected')->success()->send();
            });
    }

    protected static function approveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-m-check')
            ->color('success')
            ->visible(fn (Transaction $record): bool => $record->isPending())
            ->modalIcon('heroicon-o-check-circle')
            ->modalHeading(fn (Transaction $record): string => "Lend \"{$record->book->title}\" to {$record->user->name}?")
            ->modalDescription(fn (Transaction $record): string => $record->book->stock.' '.str('copy')->plural($record->book->stock).' on the shelf.')
            // Requested duration, fee and GCash receipt; the loan runs for the paid-for days from today
            ->modalContent(fn (Transaction $record) => view('filament.admin.borrow-request', [
                'loan' => $record,
                'receiptUrl' => $record->borrow_receipt_path
                    ? Storage::disk('receipts')->temporaryUrl($record->borrow_receipt_path, now()->addMinutes(15))
                    : null,
            ]))
            ->modalWidth('lg')
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
            ->modalDescription(fn (Transaction $record): string => "\"{$record->book->title}\" won't be lent, and {$record->user->name} is notified."
                .($record->fee_amount ? ' Remember to refund their '.Transaction::peso((float) $record->fee_amount).' fee via GCash.' : ''))
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

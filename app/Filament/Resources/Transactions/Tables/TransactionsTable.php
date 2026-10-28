<?php

namespace App\Filament\Resources\Transactions\Tables;

use App\Enums\PaymentCheck;
use App\Enums\TransactionStatus;
use App\Models\Book;
use App\Models\Transaction;
use App\Services\LibraryNotifications;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Things waiting on staff first (borrow requests, fine receipts, returns to check in), then overdue loans (most overdue on top), other loans by due date, then closed ones
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw(
                    "CASE
                        WHEN transactions.status IN ('pending', 'pending_fine_verification', 'pending_return') THEN 0
                        WHEN transactions.status = 'approved' AND transactions.return_date IS NULL AND transactions.due_date < ? THEN 1
                        WHEN transactions.status = 'approved' AND transactions.return_date IS NULL THEN 2
                        ELSE 3
                    END",
                    [today()->toDateString()]
                )
                ->orderBy('transactions.due_date'))
            ->recordClasses(fn (Transaction $record) => match (true) {
                $record->isPending(), $record->isAwaitingFineReview(), $record->isAwaitingReturn() => 'bg-transaction-pending',
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
                    ->sortable()
                    ->wrap(),
                TextColumn::make('loan_status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (Transaction $record): string => match (true) {
                        $record->isPending() && $record->duration_days => $record->status->getLabel().' · '.$record->duration_days.' days · '.$record->feeLabel(),
                        $record->isActive() => $record->dueLabel() ?? TransactionStatus::Approved->getLabel(),
                        $record->isAwaitingFineReview() => $record->status->getLabel().' · '.Transaction::peso($record->fineDue()),
                        $record->isAwaitingReturn() && $record->return_requested_at => $record->status->getLabel().' · since '.$record->return_requested_at->format('M j'),
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
                // Dates staff rarely scan are behind the column toggle, so the table fits beside the sidebar
                TextColumn::make('borrow_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->color(fn (Transaction $record): ?string => $record->isOverdue() ? 'danger' : null),
                TextColumn::make('return_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                // Ended paid requests: check the payment, then refund it (or record that it never arrived)
                TextColumn::make('refund_status')
                    ->label('Refund')
                    ->badge()
                    ->state(fn (Transaction $record): ?string => $record->needsPaymentCheck() ? Transaction::PAYMENT_UNCHECKED : $record->refund_status)
                    ->formatStateUsing(fn (?string $state, Transaction $record): ?string => match ($state) {
                        Transaction::PAYMENT_UNCHECKED => 'Check payment · '.$record->feeLabel(),
                        Transaction::REFUND_PENDING => 'Refund pending · '.$record->feeLabel(),
                        Transaction::REFUNDED => 'Refunded',
                        Transaction::REFUND_NOT_PAID => 'Payment not found',
                        default => null,
                    })
                    ->description(fn (Transaction $record): ?string => $record->isRefunded() ? $record->refunded_at?->format('M j, Y') : null)
                    ->color(fn (?string $state): string => match ($state) {
                        Transaction::REFUNDED => 'success',
                        Transaction::REFUND_NOT_PAID => 'danger',
                        Transaction::PAYMENT_UNCHECKED => 'gray',
                        default => 'warning',
                    })
                    ->icon(fn (?string $state): string => match ($state) {
                        Transaction::REFUNDED => 'heroicon-m-check-circle',
                        Transaction::REFUND_NOT_PAID => 'heroicon-m-x-circle',
                        Transaction::PAYMENT_UNCHECKED => 'heroicon-m-magnifying-glass',
                        default => 'heroicon-m-banknotes',
                    })
                    ->placeholder('—'),
                TextColumn::make('fine_status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Pending' => 'danger',
                        'Paid' => 'success',
                        default => 'gray',
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
                Filter::make('returns')
                    ->label('Returns to check in')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->awaitingReturn()),
                Filter::make('payment_checks')
                    ->label('Payments to check')
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->awaitingPaymentCheck()),
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
            // One "⋯" menu per row, so the table fits without scrolling sideways. Each action only appears for the states it handles.
            ->actions([
                ActionGroup::make([
                    static::approveAction(),
                    static::rejectAction(),
                    static::verifyPaymentAction(),
                    static::rejectPaymentAction(),
                    static::verifyReturnAction(),
                    static::rejectReturnAction(),
                    static::checkPaymentAction(),
                    static::processRefundAction(),
                    static::viewRefundReceiptAction(),
                    EditAction::make(),
                    DeleteAction::make(),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->color('gray')
                    ->tooltip('Actions'),
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
            // Only payments staff have confirmed are ever refunded
            ->visible(fn (Transaction $record): bool => $record->isRefundPending() && $record->isPaymentVerified())
            ->modalIcon('heroicon-o-banknotes')
            ->modalIconColor('warning')
            ->modalHeading(fn (Transaction $record): string => 'Refund '.$record->feeLabel()." to {$record->user->name}")
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

                    if (! $loan?->isRefundPending() || ! $loan->isPaymentVerified()) {
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

    // The student says they've handed the book in: confirm it's actually here before it goes back on the shelf
    protected static function verifyReturnAction(): Action
    {
        return Action::make('verifyReturn')
            ->label('Verify Return')
            ->icon('heroicon-m-check-badge')
            ->color('success')
            ->visible(fn (Transaction $record): bool => $record->isAwaitingReturn())
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-arrow-uturn-left')
            ->modalHeading(fn (Transaction $record): string => "Check in \"{$record->book->title}\"?")
            ->modalDescription(fn (Transaction $record): string => $record->isOverdue()
                // The student asked to return it on time, but it's now overdue: a late book shouldn't escape its fine
                ? "{$record->user->name} asked to return this on {$record->return_requested_at?->format('M j')}, and it's now {$record->daysOverdue()} ".str('day')->plural($record->daysOverdue())." overdue. If the book only reached you after its due date, choose \"Not received\" so they pay the fine."
                : "Only confirm once you have the book from {$record->user->name}. It goes back on the shelf and off their account.")
            ->modalSubmitActionLabel('Confirm & check in')
            ->action(function (Transaction $record, Action $action): void {
                $done = DB::transaction(function () use ($record): bool {
                    $loan = Transaction::lockForUpdate()->find($record->id);

                    if (! $loan?->isAwaitingReturn()) {
                        return false;
                    }

                    // Sets the return date and puts the copy back on the shelf
                    $loan->confirmReturn();

                    return true;
                });

                if (! $done) {
                    Notification::make()->title('This return has already been handled')->warning()->send();
                    $action->cancel();
                }

                app(LibraryNotifications::class)->returnDecided($record->refresh(), accepted: true);

                Notification::make()->title('Book checked in')->success()->send();
            });
    }

    // The book never reached the desk: it stays on loan to the student
    protected static function rejectReturnAction(): Action
    {
        return Action::make('rejectReturn')
            ->label('Not received')
            ->icon('heroicon-m-x-mark')
            ->color('danger')
            ->visible(fn (Transaction $record): bool => $record->isAwaitingReturn())
            ->modalIcon('heroicon-o-x-circle')
            ->modalIconColor('danger')
            ->modalHeading('Book not received?')
            ->modalDescription(fn (Transaction $record): string => "\"{$record->book->title}\" stays on loan to {$record->user->name}, and fines apply once it's overdue.")
            ->modalWidth('md')
            ->schema([
                Textarea::make('reason')
                    ->label('Reason (optional)')
                    ->placeholder('e.g. The book wasn\'t in the return box.')
                    ->rows(3)
                    ->maxLength(500),
            ])
            ->modalSubmitActionLabel('Keep it on loan')
            ->action(function (Transaction $record, array $data, Action $action): void {
                $done = DB::transaction(function () use ($record): bool {
                    $loan = Transaction::lockForUpdate()->find($record->id);

                    if (! $loan?->isAwaitingReturn()) {
                        return false;
                    }

                    $loan->rejectReturn();

                    return true;
                });

                if (! $done) {
                    Notification::make()->title('This return has already been handled')->warning()->send();
                    $action->cancel();
                }

                app(LibraryNotifications::class)->returnDecided($record->refresh(), accepted: false, reason: $data['reason'] ?? null);

                Notification::make()->title('Kept on loan')->success()->send();
            });
    }

    // A paid request ended before anyone confirmed the payment: look for the reference in the library's GCash before refunding
    protected static function checkPaymentAction(): Action
    {
        return Action::make('checkPayment')
            ->label('Check Payment')
            ->icon('heroicon-m-magnifying-glass')
            ->color('warning')
            ->visible(fn (Transaction $record): bool => $record->needsPaymentCheck())
            ->modalIcon('heroicon-o-banknotes')
            ->modalIconColor('warning')
            ->modalHeading(fn (Transaction $record): string => 'Did '.$record->feeLabel()." from {$record->user->name} arrive?")
            ->modalDescription(fn (Transaction $record): string => "Search the library's GCash history for reference {$record->gcash_reference}. Don't go by the screenshot alone: it can be edited.")
            ->modalContent(fn (Transaction $record) => view('filament.admin.refund', [
                'loan' => $record,
                'mode' => 'check',
                'imageUrl' => static::receiptUrl($record->borrow_receipt_path),
            ]))
            ->modalWidth('lg')
            ->schema([
                Radio::make('outcome')
                    ->label('In the library\'s GCash history')
                    ->options(PaymentCheck::class)
                    ->required(),
            ])
            ->modalSubmitActionLabel('Save')
            ->action(function (Transaction $record, array $data, Action $action): void {
                $loan = DB::transaction(function () use ($record, $data): ?Transaction {
                    $loan = Transaction::lockForUpdate()->find($record->id);

                    if (! $loan?->needsPaymentCheck()) {
                        return null;
                    }

                    $data['outcome'] === PaymentCheck::Received
                        ? $loan->verifyPayment(Auth::user())
                        : $loan->markPaymentNotFound();

                    return $loan;
                });

                if (! $loan) {
                    Notification::make()->title('This payment has already been checked')->warning()->send();
                    $action->cancel();
                }

                if ($loan->isRefundPending()) {
                    Notification::make()->title('Payment confirmed')->body('Send the refund, then record it with "Process Refund".')->success()->send();

                    return;
                }

                app(LibraryNotifications::class)->paymentNotFound($loan->load('user', 'book'));

                Notification::make()->title('Marked as not paid')->body("{$loan->user->name} has been told.")->success()->send();
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

    // Staff tick this on Approve (required) and Reject (optional): they searched the library's GCash history, not just the screenshot
    protected static function paymentFoundCheckbox(Transaction $record): Checkbox
    {
        return Checkbox::make('payment_found')
            ->label('I found '.$record->feeLabel()." with reference {$record->gcash_reference} in the library's GCash history")
            ->visible($record->isPaid() && ! $record->isPaymentVerified());
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
            // A screenshot can be edited: approving a paid request means staff found the money in GCash
            ->schema(fn (Transaction $record): array => [
                static::paymentFoundCheckbox($record)
                    ->accepted()
                    ->validationMessages(['accepted' => 'Check the payment arrived before approving.']),
            ])
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

                    // Starts the loan period, takes a copy off the shelf, and records who confirmed the payment
                    $loan->approve(Auth::user());

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
            ->modalDescription(fn (Transaction $record): string => "\"{$record->book->title}\" won't be lent, and {$record->user->name} is notified.")
            ->modalWidth('md')
            ->schema(fn (Transaction $record): array => [
                Textarea::make('reason')
                    ->label('Reason (optional)')
                    ->placeholder('e.g. All copies are reserved for a class this week.')
                    ->rows(3)
                    ->maxLength(500),
                // Only a payment someone has found gets refunded; otherwise it waits under "Payments to check"
                static::paymentFoundCheckbox($record)
                    ->helperText('Tick to queue the refund now. Leave it if you haven\'t checked yet.'),
            ])
            ->modalSubmitActionLabel('Reject request')
            ->action(function (Transaction $record, array $data, Action $action): void {
                $done = DB::transaction(function () use ($record, $data): bool {
                    $loan = Transaction::lockForUpdate()->find($record->id);

                    if (! $loan?->isPending()) {
                        return false;
                    }

                    $loan->reject(Auth::user(), paymentFound: (bool) ($data['payment_found'] ?? false));

                    return true;
                });

                if (! $done) {
                    Notification::make()->title("Can't reject this request")->body('This request has already been handled.')->danger()->send();
                    $action->cancel();
                }

                app(LibraryNotifications::class)->borrowDecided($record->refresh(), $data['reason'] ?? null);

                Notification::make()->title('Request rejected')->success()->send();
            });
    }
}

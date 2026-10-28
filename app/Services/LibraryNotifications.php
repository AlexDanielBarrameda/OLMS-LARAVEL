<?php

namespace App\Services;

use App\Enums\BookRequestStatus;
use App\Enums\TransactionStatus;
use App\Filament\Resources\BookRequests\BookRequestResource;
use App\Filament\Resources\Books\BookResource;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Filament\User\Pages\UserDashboard;
use App\Models\Book;
use App\Models\BookRequest;
use App\Models\Transaction;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

// In-app (database) notifications for the borrowing and acquisition workflows
class LibraryNotifications
{
    public function lowStock(Book $book): void
    {
        $outOfStock = $book->stock <= 0;

        Notification::make()
            ->title("\"{$book->title}\" is ".($outOfStock ? 'out of stock' : 'low on stock'))
            ->body($outOfStock ? 'No copies left to lend.' : $book->stock.' '.str('copy')->plural($book->stock).' left.')
            ->icon($outOfStock ? 'heroicon-o-archive-box-x-mark' : 'heroicon-o-archive-box')
            ->iconColor($outOfStock ? 'danger' : 'warning')
            ->actions([
                Action::make('view')
                    ->label('View book')
                    ->url(BookResource::getUrl('edit', ['record' => $book], panel: 'admin'))
                    ->markAsRead(),
            ])
            ->sendToDatabase(User::staff()->get());
    }

    public function borrowRequested(Transaction $loan): void
    {
        Notification::make()
            ->title('New borrow request')
            ->body(static::requestBody($loan))
            ->icon('heroicon-o-inbox-arrow-down')
            ->iconColor('info')
            ->viewData(['transaction_id' => $loan->id])
            ->actions([
                Action::make('review')
                    ->label('Review requests')
                    ->url(static::pendingRequestsUrl())
                    ->markAsRead(),
            ])
            ->sendToDatabase(User::staff()->get());
    }

    // The student withdrew the request: take the stale "New borrow request" alerts down and say why
    public function borrowCancelled(Transaction $loan): void
    {
        static::requestAlerts($loan)->delete();

        Notification::make()
            ->title('Borrow request cancelled')
            ->body("{$loan->user->name} cancelled their request for \"{$loan->book->title}\".")
            ->icon('heroicon-o-x-circle')
            ->iconColor('gray')
            ->sendToDatabase(User::staff()->get());
    }

    public function borrowDecided(Transaction $loan, ?string $reason = null): void
    {
        // Someone handled it, so the other staff don't need to act on the alert
        static::requestAlerts($loan)->whereNull('read_at')->update(['read_at' => now()]);

        $title = $loan->book->title;

        $notification = $loan->status === TransactionStatus::Approved
            ? Notification::make()
                ->success()
                ->title("Your request for \"{$title}\" was approved")
                ->body('Due back on '.Carbon::parse($loan->due_date)->format('M j').'.')
            : Notification::make()
                ->danger()
                ->title("Your request for \"{$title}\" was declined")
                ->body(filled($reason) ? $reason : 'Please contact the library for details.');

        $notification
            ->actions([
                Action::make('view')
                    ->label('View my books')
                    ->url(UserDashboard::getUrl(panel: 'user'))
                    ->markAsRead(),
            ])
            ->sendToDatabase($loan->user);
    }

    public function bookRequested(BookRequest $request): void
    {
        Notification::make()
            ->title('New book request')
            ->body("{$request->requester->name} requested \"{$request->title}\" by {$request->author}.")
            ->icon('heroicon-o-clipboard-document-list')
            ->iconColor('info')
            ->actions([
                Action::make('review')
                    ->label('Review')
                    ->url(BookRequestResource::getUrl('index', panel: 'admin'))
                    ->markAsRead(),
            ])
            ->sendToDatabase(User::where('role', 'super_admin')->get());
    }

    public function bookRequestDecided(BookRequest $request): void
    {
        $approved = $request->status === BookRequestStatus::Approved;
        $book = $approved ? $request->book : null;

        Notification::make()
            ->status($approved ? 'success' : 'danger')
            ->title("Your request for \"{$request->title}\" was ".($approved ? 'approved' : 'declined'))
            ->body(match (true) {
                $book?->isOnOrder() => 'It\'s in the catalog as "On Order". Add the ISBN and copies when it arrives.',
                $book !== null => 'The library already has this title, so it\'s linked to the existing book.',
                default => 'An admin reviewed it and decided not to order it.',
            })
            ->actions([
                $book
                    ? Action::make('edit')
                        ->label('Edit book')
                        ->url(BookResource::getUrl('edit', ['record' => $book], panel: 'admin'))
                        ->markAsRead()
                    : Action::make('view')
                        ->label('View requests')
                        ->url(BookRequestResource::getUrl('index', panel: 'admin'))
                        ->markAsRead(),
            ])
            ->sendToDatabase($request->requester);
    }

    protected static function requestBody(Transaction $loan): string
    {
        return "{$loan->user->name} wants to borrow \"{$loan->book->title}\".";
    }

    // Staff "New borrow request" alerts for this loan. Older alerts weren't tagged with the transaction, so match those by their text.
    protected static function requestAlerts(Transaction $loan): QueryBuilder
    {
        return DB::table('notifications')->where(fn (QueryBuilder $query) => $query
            ->where('data->viewData->transaction_id', $loan->id)
            ->orWhere(fn (QueryBuilder $legacy) => $legacy
                ->where('data->title', 'New borrow request')
                ->where('data->body', static::requestBody($loan))));
    }

    // Transactions list with the "Pending requests" filter switched on
    public static function pendingRequestsUrl(): string
    {
        return TransactionResource::getUrl('index', ['filters' => ['pending' => ['isActive' => true]]], panel: 'admin');
    }
}

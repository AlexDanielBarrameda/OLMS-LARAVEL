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
use Illuminate\Support\Carbon;

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
            ->body("{$loan->user->name} wants to borrow \"{$loan->book->title}\".")
            ->icon('heroicon-o-inbox-arrow-down')
            ->iconColor('info')
            ->actions([
                Action::make('review')
                    ->label('Review requests')
                    ->url(static::pendingRequestsUrl())
                    ->markAsRead(),
            ])
            ->sendToDatabase(User::staff()->get());
    }

    public function borrowDecided(Transaction $loan, ?string $reason = null): void
    {
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

        Notification::make()
            ->status($approved ? 'success' : 'danger')
            ->title("Your request for \"{$request->title}\" was ".($approved ? 'approved' : 'declined'))
            ->body($approved ? 'The library will add it to the catalog.' : 'An admin reviewed it and decided not to order it.')
            ->actions([
                Action::make('view')
                    ->label('View requests')
                    ->url(BookRequestResource::getUrl('index', panel: 'admin'))
                    ->markAsRead(),
            ])
            ->sendToDatabase($request->requester);
    }

    // Transactions list with the "Pending requests" filter switched on
    public static function pendingRequestsUrl(): string
    {
        return TransactionResource::getUrl('index', ['filters' => ['pending' => ['isActive' => true]]], panel: 'admin');
    }
}

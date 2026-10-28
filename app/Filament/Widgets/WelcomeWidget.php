<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\BookRequests\BookRequestResource;
use App\Models\BookRequest;
use App\Models\Transaction;
use App\Services\LibraryNotifications;
use Filament\Widgets\Widget;

// Greeting + shortcuts to whatever is waiting for this person (replaces the account widget and its Sign out button)
class WelcomeWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = -10;

    protected int | string | array $columnSpan = 'full';

    protected string $view = 'filament.widgets.welcome-widget';

    protected function getViewData(): array
    {
        $user = auth()->user();
        $hour = now()->hour;

        return [
            'user' => $user,
            'greeting' => match (true) {
                $hour < 12 => 'Good morning',
                $hour < 18 => 'Good afternoon',
                default => 'Good evening',
            },
            'pendingLoans' => Transaction::pending()->count(),
            'pendingLoansUrl' => LibraryNotifications::pendingRequestsUrl(),
            'finesToVerify' => Transaction::awaitingFineReview()->count(),
            'finesUrl' => LibraryNotifications::fineReviewUrl(),
            'refundsToProcess' => Transaction::refundPending()->count(),
            'refundsUrl' => LibraryNotifications::refundsUrl(),
            // Only super admins review acquisition requests
            'pendingBookRequests' => $user->isSuperAdmin() ? BookRequest::where('status', 'pending')->count() : 0,
            'bookRequestsUrl' => BookRequestResource::getUrl('index'),
        ];
    }
}

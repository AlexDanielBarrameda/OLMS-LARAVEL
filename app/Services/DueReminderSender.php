<?php

namespace App\Services;

use App\Filament\User\Pages\UserDashboard;
use App\Models\Transaction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

// Sends in-app reminders for overdue and soon-due loans, at most once per loan per day
class DueReminderSender
{
    /**
     * @param  Builder<Transaction>|null  $loans  Narrow the loans to check (e.g. one user's); defaults to all
     * @return array{overdue: int, dueSoon: int, alreadySent: int}
     */
    public function send(?Builder $loans = null): array
    {
        $counts = ['overdue' => 0, 'dueSoon' => 0, 'alreadySent' => 0];

        ($loans ?? Transaction::query())
            ->needsReminder()
            ->with(['user', 'book'])
            ->lazyById()
            ->each(function (Transaction $loan) use (&$counts): void {
                if (! $loan->user || ! $loan->book) {
                    return;
                }

                $key = "due-reminder:{$loan->id}:".today()->toDateString();

                if ($loan->user->notifications()->where('data->viewData->reminder_key', $key)->exists()) {
                    $counts['alreadySent']++;

                    return;
                }

                $this->reminderFor($loan)
                    ->viewData(['reminder_key' => $key])
                    ->sendToDatabase($loan->user);

                $loan->isOverdue() ? $counts['overdue']++ : $counts['dueSoon']++;
            });

        return $counts;
    }

    protected function reminderFor(Transaction $loan): Notification
    {
        $title = $loan->book->title;
        $dueOn = Carbon::parse($loan->due_date)->format('M j');
        $days = $loan->daysUntilDue();

        $notification = $loan->isOverdue()
            ? Notification::make()
                ->danger()
                ->title("\"{$title}\" is ".abs($days).' '.str('day')->plural(abs($days)).' overdue')
                ->body("It was due on {$dueOn}. Please return it soon. You can't borrow other books until it's back.")
            : Notification::make()
                ->warning()
                ->icon('heroicon-o-clock')
                ->title("\"{$title}\" is ".lcfirst($loan->dueLabel()))
                ->body("Please return it by {$dueOn}.");

        return $notification->actions([
            Action::make('view')
                ->label('View my books')
                ->url(UserDashboard::getUrl(panel: 'user'))
                ->markAsRead(),
        ]);
    }
}

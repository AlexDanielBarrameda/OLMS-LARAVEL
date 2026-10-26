<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\DueReminderSender;
use Illuminate\Console\Command;

class SendDueReminders extends Command
{
    protected $signature = 'app:send-due-reminders';

    protected $description = 'Send in-app reminders for borrowed books that are overdue or due within '.Transaction::DUE_SOON_DAYS.' days';

    public function handle(DueReminderSender $sender): int
    {
        ['overdue' => $overdue, 'dueSoon' => $dueSoon, 'alreadySent' => $alreadySent] = $sender->send();

        $this->info('Sent '.($overdue + $dueSoon)." reminders ({$overdue} overdue, {$dueSoon} due soon). Skipped {$alreadySent} already sent today.");

        return self::SUCCESS;
    }
}

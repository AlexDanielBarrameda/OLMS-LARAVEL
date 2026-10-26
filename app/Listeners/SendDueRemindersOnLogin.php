<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\DueReminderSender;
use Illuminate\Auth\Events\Login;

// Catches users up on reminders when they sign in, so they don't depend on the daily scheduler having run
class SendDueRemindersOnLogin
{
    public function __construct(protected DueReminderSender $sender) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $this->sender->send($event->user->transactions()->getQuery());
    }
}

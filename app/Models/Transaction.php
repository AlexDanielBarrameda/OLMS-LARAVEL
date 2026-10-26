<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Transaction extends Model
{
    // Reminders start this many days before the due date
    public const DUE_SOON_DAYS = 3;

    protected $fillable = ['user_id', 'book_id', 'borrow_date', 'due_date', 'return_date', 'fine_status'];

    // Let's also set up the relationships so Filament can link them easily
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    // Still out: not returned by the user (fine_status) or checked in by staff (return_date)
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('fine_status', '!=', 'Returned')->whereNull('return_date');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->active()->whereDate('due_date', '<', today()->toDateString());
    }

    // Overdue or due within DUE_SOON_DAYS
    public function scopeNeedsReminder(Builder $query): Builder
    {
        return $query->active()->whereDate('due_date', '<=', today()->addDays(static::DUE_SOON_DAYS)->toDateString());
    }

    public function isActive(): bool
    {
        return $this->fine_status !== 'Returned' && blank($this->return_date);
    }

    public function isOverdue(): bool
    {
        return $this->isActive() && $this->daysUntilDue() < 0;
    }

    // Whole days until the due date: 0 on the due date, negative once overdue
    public function daysUntilDue(): int
    {
        return (int) today()->diffInDays(Carbon::parse($this->due_date)->startOfDay(), false);
    }

    // "Overdue · 3 days", "Due today", "Due tomorrow", "Due in 2 days", or null when not due soon
    public function dueLabel(): ?string
    {
        if (! $this->isActive()) {
            return null;
        }

        $days = $this->daysUntilDue();

        return match (true) {
            $days < 0 => 'Overdue · '.abs($days).' '.str('day')->plural(abs($days)),
            $days === 0 => 'Due today',
            $days === 1 => 'Due tomorrow',
            $days <= static::DUE_SOON_DAYS => "Due in {$days} days",
            default => null,
        };
    }
}

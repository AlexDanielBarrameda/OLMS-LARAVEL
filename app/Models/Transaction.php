<?php

namespace App\Models;

use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Transaction extends Model
{
    use LogsActivity;

    // Reminders start this many days before the due date
    public const DUE_SOON_DAYS = 3;

    public const LOAN_DAYS = 14;

    protected $fillable = ['user_id', 'book_id', 'status', 'borrow_date', 'due_date', 'return_date', 'fine_status'];

    protected function casts(): array
    {
        return [
            'status' => TransactionStatus::class,
        ];
    }

    protected static function booted(): void
    {
        // Keep status and return_date in step, whichever one was edited
        static::saving(function (Transaction $loan): void {
            if ($loan->status === TransactionStatus::Approved && filled($loan->return_date)) {
                $loan->status = TransactionStatus::Returned;
            }

            if ($loan->status === TransactionStatus::Returned && blank($loan->return_date)) {
                $loan->return_date = today()->toDateString();
            }
        });

        // A copy is off the shelf exactly while a loan is approved, however the status changes
        static::created(function (Transaction $loan): void {
            if ($loan->holdsCopy()) {
                $loan->book?->decrement('stock');
            }
        });

        static::updated(function (Transaction $loan): void {
            if (! $loan->wasChanged('status')) {
                return;
            }

            $heldBefore = $loan->getOriginal('status') === TransactionStatus::Approved;

            if ($loan->holdsCopy() && ! $heldBefore) {
                $loan->book?->decrement('stock');
            } elseif (! $loan->holdsCopy() && $heldBefore) {
                $loan->book?->increment('stock');
            }
        });

        static::deleted(function (Transaction $loan): void {
            if ($loan->holdsCopy()) {
                $loan->book?->increment('stock');
            }
        });
    }

    // Let's also set up the relationships so Filament can link them easily
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', TransactionStatus::Pending->value);
    }

    // On loan right now: approved and not returned
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', TransactionStatus::Approved->value)->whereNull('return_date');
    }

    // Requested or on loan: what counts toward a user's borrowing limit
    public function scopeOpen(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [TransactionStatus::Pending->value, TransactionStatus::Approved->value])
            ->whereNull('return_date');
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

    // Ever lent out (approved, whether still out or since returned)
    public function scopeLent(Builder $query): Builder
    {
        return $query->whereIn('status', [TransactionStatus::Approved->value, TransactionStatus::Returned->value]);
    }

    public function holdsCopy(): bool
    {
        return $this->status === TransactionStatus::Approved;
    }

    public function isPending(): bool
    {
        return $this->status === TransactionStatus::Pending;
    }

    public function isActive(): bool
    {
        return $this->status === TransactionStatus::Approved && blank($this->return_date);
    }

    public function isOverdue(): bool
    {
        return $this->isActive() && $this->daysUntilDue() < 0;
    }

    // Lend the copy: the loan period starts on approval, not on the request
    public function approve(): void
    {
        $this->update([
            'status' => TransactionStatus::Approved,
            'borrow_date' => today()->toDateString(),
            'due_date' => today()->addDays(static::LOAN_DAYS)->toDateString(),
        ]);
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

    // Audit trail for the Activity Log: who changed what (only the fields that actually changed)
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('library')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event): string => "Transaction {$event}");
    }
}

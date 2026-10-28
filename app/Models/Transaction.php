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

    // Overdue fine, in PHP, per day late
    public const FINE_PER_DAY = 5.00;

    // Borrowing fee, in PHP, per 7 days
    public const FEE_PER_WEEK = 15.00;

    // Durations offered when requesting a book (days => label); "custom" takes any multiple of 7 up to MAX_DURATION_DAYS
    public const DURATION_PRESETS = [
        7 => '7 days',
        14 => '14 days',
        31 => '1 Month Promo (31 days)',
    ];

    // Preset prices. The month promo charges 28 days' price (₱60) for 31 days, so it can't come from the per-week rate.
    public const PRESET_FEES = [
        7 => 15.00,
        14 => 30.00,
        31 => 60.00,
    ];

    public const MAX_DURATION_DAYS = 84;

    public const REFUND_PENDING = 'pending';

    public const REFUNDED = 'refunded';

    // Staff looked for the GCash reference and the money never arrived: nothing to refund
    public const REFUND_NOT_PAID = 'not_paid';

    // Shown in the Refund column for an ended paid request nobody has checked yet. Never stored: refund_status is null until staff decide.
    public const PAYMENT_UNCHECKED = 'unchecked';

    // A GCash "Ref No." is exactly 13 digits (the column, the form and the validation all use this)
    public const GCASH_REFERENCE_LENGTH = 13;

    protected $fillable = ['user_id', 'book_id', 'status', 'borrow_date', 'due_date', 'duration_days', 'fee_amount', 'gcash_reference', 'payment_verified_at', 'payment_verified_by', 'borrow_receipt_path', 'refund_status', 'refund_receipt_path', 'refunded_at', 'return_date', 'return_requested_at', 'fine_status', 'payment_receipt_path', 'fine_amount'];

    protected function casts(): array
    {
        return [
            'status' => TransactionStatus::class,
            'duration_days' => 'integer',
            'fee_amount' => 'decimal:2',
            'fine_amount' => 'decimal:2',
            'refunded_at' => 'datetime',
            'payment_verified_at' => 'datetime',
            'return_requested_at' => 'datetime',
        ];
    }

    // Preset durations use their listed price (e.g. the 31-day promo is ₱60); custom ones are ₱15 per 7 days
    public static function feeFor(int $days): float
    {
        return static::PRESET_FEES[$days] ?? round($days / 7 * static::FEE_PER_WEEK, 2);
    }

    // A duration a student may request: one of the presets, or a custom multiple of 7 within the limit
    public static function isValidDuration(int $days, bool $custom): bool
    {
        return $custom
            ? $days >= 7 && $days <= static::MAX_DURATION_DAYS && $days % 7 === 0
            : array_key_exists($days, static::DURATION_PRESETS);
    }

    protected static function booted(): void
    {
        // Keep status and return_date in step, whichever one was edited
        static::saving(function (Transaction $loan): void {
            // A paid request that's rejected or cancelled is owed its fee back, whichever screen did it,
            // but only once staff have confirmed the money actually arrived (a screenshot alone proves nothing)
            if ($loan->status?->owesRefund() && $loan->isPaid() && $loan->isPaymentVerified() && blank($loan->refund_status)) {
                $loan->refund_status = static::REFUND_PENDING;
            }

            if ($loan->status?->holdsCopy() && filled($loan->return_date)) {
                $loan->status = TransactionStatus::Returned;
            }

            if ($loan->status === TransactionStatus::Returned && blank($loan->return_date)) {
                $loan->return_date = today()->toDateString();
            }
        });

        // A copy is off the shelf exactly while the student has the book (on loan, or fine under review), however the status changes
        static::created(function (Transaction $loan): void {
            if ($loan->holdsCopy()) {
                $loan->book?->decrement('stock');
            }
        });

        static::updated(function (Transaction $loan): void {
            if (! $loan->wasChanged('status')) {
                return;
            }

            $heldBefore = (bool) $loan->getOriginal('status')?->holdsCopy();

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

    // Requested, on loan, fine under review, or return not yet checked in: what counts toward a user's borrowing limit
    public function scopeOpen(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [TransactionStatus::Pending->value, ...static::copyHoldingStatuses()])
            ->whereNull('return_date');
    }

    // Books not back on the shelf: on loan, awaiting fine verification, or handed in but not checked in
    public function scopeCheckedOut(Builder $query): Builder
    {
        return $query
            ->whereIn('status', static::copyHoldingStatuses())
            ->whereNull('return_date');
    }

    // Returns waiting for staff to check the book in
    public function scopeAwaitingReturn(Builder $query): Builder
    {
        return $query->where('status', TransactionStatus::PendingReturn->value);
    }

    // Paid requests rejected or cancelled before staff confirmed the payment: someone has to check GCash before any refund
    public function scopeAwaitingPaymentCheck(Builder $query): Builder
    {
        return $query
            ->whereIn('status', [TransactionStatus::Rejected->value, TransactionStatus::Cancelled->value])
            ->where('fee_amount', '>', 0)
            ->whereNull('payment_verified_at')
            ->whereNull('refund_status');
    }

    /** @return list<string> */
    protected static function copyHoldingStatuses(): array
    {
        return collect(TransactionStatus::cases())
            ->filter(fn (TransactionStatus $status): bool => $status->holdsCopy())
            ->map(fn (TransactionStatus $status): string => $status->value)
            ->values()
            ->all();
    }

    // GCash receipts waiting for staff to check
    public function scopeAwaitingFineReview(Builder $query): Builder
    {
        return $query->where('status', TransactionStatus::PendingFineVerification->value);
    }

    // Paid, then rejected or cancelled, and not paid back yet
    public function scopeRefundPending(Builder $query): Builder
    {
        return $query->where('refund_status', static::REFUND_PENDING);
    }

    public function isRefundPending(): bool
    {
        return $this->refund_status === static::REFUND_PENDING;
    }

    public function isRefunded(): bool
    {
        return $this->refund_status === static::REFUNDED;
    }

    public function isPaid(): bool
    {
        return (float) $this->fee_amount > 0;
    }

    public function isPaymentVerified(): bool
    {
        return $this->payment_verified_at !== null;
    }

    // An ended paid request whose payment nobody has confirmed yet
    public function needsPaymentCheck(): bool
    {
        return (bool) $this->status?->owesRefund() && $this->isPaid() && ! $this->isPaymentVerified() && blank($this->refund_status);
    }

    // Staff found the GCash reference in the library's account. On a rejected/cancelled request this queues the refund (see saving()).
    public function verifyPayment(User $staff): void
    {
        $this->update(['payment_verified_at' => now(), 'payment_verified_by' => $staff->id]);
    }

    // Staff couldn't find the payment: close the request without a refund
    public function markPaymentNotFound(): void
    {
        $this->update(['refund_status' => static::REFUND_NOT_PAID]);
    }

    // Staff sent the fee back via GCash; the screenshot is the proof
    public function markRefunded(string $refundReceiptPath): void
    {
        $this->update([
            'refund_status' => static::REFUNDED,
            'refund_receipt_path' => $refundReceiptPath,
            'refunded_at' => now(),
        ]);
    }

    // Past due and not back on the shelf. A requested return or a fine receipt still counts until staff confirm it,
    // so neither a claimed return nor an unchecked (possibly fake) receipt lifts the overdue block.
    public function scopeOverdue(Builder $query): Builder
    {
        return $query
            ->whereIn('status', static::copyHoldingStatuses())
            ->whereNull('return_date')
            ->whereDate('due_date', '<', today()->toDateString());
    }

    // Overdue or due within DUE_SOON_DAYS
    public function scopeNeedsReminder(Builder $query): Builder
    {
        return $query->active()->whereDate('due_date', '<=', today()->addDays(static::DUE_SOON_DAYS)->toDateString());
    }

    // Ever lent out (approved, whether still out or since returned)
    public function scopeLent(Builder $query): Builder
    {
        return $query->whereIn('status', [...static::copyHoldingStatuses(), TransactionStatus::Returned->value]);
    }

    public function holdsCopy(): bool
    {
        return (bool) $this->status?->holdsCopy();
    }

    public function isPending(): bool
    {
        return $this->status === TransactionStatus::Pending;
    }

    public function isAwaitingFineReview(): bool
    {
        return $this->status === TransactionStatus::PendingFineVerification;
    }

    public function isAwaitingReturn(): bool
    {
        return $this->status === TransactionStatus::PendingReturn;
    }

    // The student says they've handed the book in. It stays checked out (and counts toward their limit) until staff check it in.
    public function requestReturn(): void
    {
        $this->update([
            'status' => TransactionStatus::PendingReturn,
            'return_requested_at' => now(),
        ]);
    }

    // Staff have the book: back on the shelf
    public function confirmReturn(): void
    {
        $this->update(['status' => TransactionStatus::Returned]);
    }

    // Staff don't have the book: it's still on loan (and accrues fines once overdue)
    public function rejectReturn(): void
    {
        $this->update([
            'status' => TransactionStatus::Approved,
            'return_requested_at' => null,
        ]);
    }

    // Days past the due date (0 if not overdue)
    public function daysOverdue(): int
    {
        return max(0, -$this->daysUntilDue());
    }

    // What's owed today; once a receipt is submitted, the amount charged then
    public function fineDue(): float
    {
        return $this->isAwaitingFineReview() && $this->fine_amount !== null
            ? (float) $this->fine_amount
            : $this->daysOverdue() * static::FINE_PER_DAY;
    }

    public static function peso(float $amount): string
    {
        return '₱'.number_format($amount, 2);
    }

    // The borrowing fee, e.g. "₱30.00"
    public function feeLabel(): string
    {
        return static::peso((float) $this->fee_amount);
    }

    // Student paid via GCash: lock in the fine and wait for staff to verify (the book isn't returned yet)
    public function submitFinePayment(string $receiptPath): void
    {
        $this->update([
            'fine_amount' => $this->daysOverdue() * static::FINE_PER_DAY,
            'payment_receipt_path' => $receiptPath,
            'fine_status' => 'Pending',
            'status' => TransactionStatus::PendingFineVerification,
        ]);
    }

    // Staff checked the receipt and have the book back
    public function confirmFineAndReturn(): void
    {
        $this->update([
            'fine_status' => 'Paid',
            'status' => TransactionStatus::Returned,
        ]);
    }

    // The receipt didn't check out: back to an ordinary overdue loan so the student can pay again
    public function rejectFinePayment(): void
    {
        $this->update([
            'fine_status' => 'None',
            'fine_amount' => null,
            'status' => TransactionStatus::Approved,
        ]);
    }

    public function isActive(): bool
    {
        return $this->status === TransactionStatus::Approved && blank($this->return_date);
    }

    // Same rule as scopeOverdue(): an unconfirmed return or fine payment past its due date is still overdue
    public function isOverdue(): bool
    {
        return $this->holdsCopy() && blank($this->return_date) && $this->daysUntilDue() < 0;
    }

    // Lend the copy: the paid-for period starts on approval, not on the request. Approving a paid request confirms its payment.
    public function approve(User $staff): void
    {
        if ($this->isPaid() && ! $this->isPaymentVerified()) {
            $this->verifyPayment($staff);
        }

        $this->update([
            'status' => TransactionStatus::Approved,
            'borrow_date' => today()->toDateString(),
            'due_date' => today()->addDays($this->loanDays())->toDateString(),
        ]);
    }

    // Turn the request down. If staff found the payment, the refund is queued (see saving()); otherwise it waits for a payment check.
    public function reject(User $staff, bool $paymentFound): void
    {
        if ($paymentFound && $this->isPaid() && ! $this->isPaymentVerified()) {
            $this->verifyPayment($staff);
        }

        $this->update(['status' => TransactionStatus::Rejected]);
    }

    // Days the student paid for (requests from before paid borrowing get the old default)
    public function loanDays(): int
    {
        return $this->duration_days ?: static::LOAN_DAYS;
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

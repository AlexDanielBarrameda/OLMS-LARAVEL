<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum TransactionStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    // Overdue book: the student paid the fine via GCash and uploaded a receipt; staff still have to check it and take the book back
    case PendingFineVerification = 'pending_fine_verification';
    case Rejected = 'rejected';
    // The student withdrew their request (kept, not deleted, so a paid fee can be refunded and audited)
    case Cancelled = 'cancelled';
    case Returned = 'returned';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Awaiting approval',
            self::Approved => 'On loan',
            self::PendingFineVerification => 'Fine under review',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Returned => 'Returned',
        };
    }

    // Past-tense verb for activity feeds
    public function activityLabel(): string
    {
        return match ($this) {
            self::Pending => 'Requested',
            self::Approved => 'Approved',
            self::PendingFineVerification => 'Fine paid',
            self::Rejected => 'Rejected',
            self::Cancelled => 'Cancelled',
            self::Returned => 'Returned',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'success',
            self::PendingFineVerification => 'info',
            self::Rejected => 'danger',
            self::Cancelled, self::Returned => 'gray',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-m-clock',
            self::Approved => 'heroicon-m-check-circle',
            self::PendingFineVerification => 'heroicon-m-receipt-percent',
            self::Rejected => 'heroicon-m-x-circle',
            self::Cancelled => 'heroicon-m-minus-circle',
            self::Returned => 'heroicon-m-arrow-uturn-left',
        };
    }

    // Paid requests that end this way are owed their fee back
    public function owesRefund(): bool
    {
        return $this === self::Rejected || $this === self::Cancelled;
    }

    // The student still has the book: it's off the shelf
    public function holdsCopy(): bool
    {
        return $this === self::Approved || $this === self::PendingFineVerification;
    }
}

<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

// What staff found when they searched the library's GCash history for a student's reference number
enum PaymentCheck: string implements HasLabel
{
    case Received = 'received';
    case NotFound = 'not_found';

    public function getLabel(): string
    {
        return match ($this) {
            self::Received => 'Found it: the amount and reference match (queue the refund)',
            self::NotFound => 'Not found: no refund',
        };
    }
}

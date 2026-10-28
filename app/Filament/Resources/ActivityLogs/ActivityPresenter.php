<?php

namespace App\Filament\Resources\ActivityLogs;

use App\Enums\BookRequestStatus;
use App\Enums\TransactionStatus;
use App\Models\Book;
use App\Models\BookRequest;
use App\Models\Transaction;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

// Turns raw activity-log rows into readable labels for the admin tables
class ActivityPresenter
{
    // Models whose changes are logged, with the name shown to admins
    public const SUBJECT_TYPES = [
        Transaction::class => 'Transaction',
        Book::class => 'Book',
        BookRequest::class => 'Book request',
    ];

    public static function subjectType(Activity $activity): string
    {
        return static::SUBJECT_TYPES[$activity->subject_type] ?? class_basename((string) $activity->subject_type);
    }

    // What was changed, e.g. "Dune" or "user1 · Dune"; falls back to the logged values once the record is deleted
    public static function subjectLabel(Activity $activity): string
    {
        $subject = $activity->subject;

        $label = match (true) {
            $subject instanceof Transaction => trim(($subject->user?->name ?? 'Unknown user').' · '.($subject->book?->title ?? 'Unknown book')),
            $subject instanceof Book, $subject instanceof BookRequest => $subject->title,
            default => $activity->properties['attributes']['title'] ?? $activity->properties['old']['title'] ?? null,
        };

        return $label ?? '#'.$activity->subject_id.($subject ? '' : ' (deleted)');
    }

    /**
     * @return list<array{field: string, old: ?string, new: ?string}>
     */
    public static function changes(Activity $activity): array
    {
        $new = $activity->properties['attributes'] ?? [];
        $old = $activity->properties['old'] ?? [];

        return collect(array_keys($new + $old))
            ->map(fn (string $field) => [
                'field' => static::fieldLabel($field),
                'old' => array_key_exists($field, $old) ? static::value($field, $old[$field], $activity->subject_type) : null,
                'new' => array_key_exists($field, $new) ? static::value($field, $new[$field], $activity->subject_type) : null,
            ])
            ->values()
            ->all();
    }

    // Short summary for the table, e.g. "Status, Due date"
    public static function changedFields(Activity $activity): ?string
    {
        $fields = array_column(static::changes($activity), 'field');

        return $fields ? implode(', ', $fields) : null;
    }

    protected static function fieldLabel(string $field): string
    {
        return match ($field) {
            'isbn' => 'ISBN',
            'user_id' => 'User',
            'book_id' => 'Book',
            'requested_by' => 'Requested by',
            'reviewed_by' => 'Reviewed by',
            'reason_for_request' => 'Reason',
            default => str($field)->replace('_', ' ')->ucfirst()->toString(),
        };
    }

    protected static function value(string $field, mixed $value, ?string $subjectType): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match (true) {
            in_array($field, ['user_id', 'requested_by', 'reviewed_by']) => User::find($value)?->name ?? "#{$value}",
            $field === 'book_id' => Book::find($value)?->title ?? "#{$value}",
            // "status" means a different thing on each model
            $field === 'status' && $subjectType === Transaction::class => TransactionStatus::tryFrom((string) $value)?->getLabel() ?? (string) $value,
            $field === 'status' && $subjectType === BookRequest::class => BookRequestStatus::tryFrom((string) $value)?->getLabel() ?? (string) $value,
            default => is_scalar($value) ? (string) $value : json_encode($value),
        };
    }
}

<?php

namespace App\Models;

use App\Enums\BookRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

// A librarian's request for the library to acquire a new title, reviewed by a super admin
class BookRequest extends Model
{
    use LogsActivity;

    protected $fillable = ['title', 'author', 'reason_for_request', 'status', 'book_id', 'requested_by', 'reviewed_by', 'reviewed_at'];

    protected function casts(): array
    {
        return [
            'status' => BookRequestStatus::class,
            'reviewed_at' => 'datetime',
        ];
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // The catalog entry created (or matched) when the request was approved
    public function book()
    {
        return $this->belongsTo(Book::class);
    }

    public function isPending(): bool
    {
        return $this->status === BookRequestStatus::Pending;
    }

    // Approve and put the title in the catalog as "On Order" (0 copies) so a librarian can finish it when it arrives.
    // If the library already has this exact title and author, link to that book instead of creating a duplicate.
    public function approve(User $reviewer): Book
    {
        return DB::transaction(function () use ($reviewer): Book {
            $book = Book::query()
                ->whereRaw('LOWER(title) = ?', [mb_strtolower(trim($this->title))])
                ->whereRaw('LOWER(author) = ?', [mb_strtolower(trim($this->author))])
                ->first()
                ?? Book::create([
                    'title' => trim($this->title),
                    'author' => trim($this->author),
                    'isbn' => null,
                    'stock' => 0,
                    'status' => Book::STATUS_ON_ORDER,
                ]);

            $this->update(['book_id' => $book->id]);
            // Replace any eager-loaded (empty) relation so callers see the new book
            $this->setRelation('book', $book);
            $this->review(BookRequestStatus::Approved, $reviewer);

            return $book;
        });
    }

    public function review(BookRequestStatus $decision, User $reviewer): void
    {
        $this->update([
            'status' => $decision,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);
    }

    // Audit trail for the Activity Log: who changed what (only the fields that actually changed)
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('library')
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event): string => "Book request {$event}");
    }
}

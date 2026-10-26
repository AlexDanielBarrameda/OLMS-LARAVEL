<?php

namespace App\Models;

use App\Enums\BookRequestStatus;
use Illuminate\Database\Eloquent\Model;

// A librarian's request for the library to acquire a new title, reviewed by a super admin
class BookRequest extends Model
{
    protected $fillable = ['title', 'author', 'reason_for_request', 'status', 'requested_by', 'reviewed_by', 'reviewed_at'];

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

    public function isPending(): bool
    {
        return $this->status === BookRequestStatus::Pending;
    }

    public function review(BookRequestStatus $decision, User $reviewer): void
    {
        $this->update([
            'status' => $decision,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ]);
    }
}

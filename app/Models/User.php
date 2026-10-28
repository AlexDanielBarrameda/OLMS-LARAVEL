<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\Traits\CausesActivity;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    // CausesActivity: $user->actions() lists the activity-log entries this user caused
    use CausesActivity, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'status',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    
    public const MAX_ACTIVE_LOANS = 3;

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    // Uses a loaded transactions_count when the query has one (e.g. the Users table), so per-row checks don't query
    public function hasTransactions(): bool
    {
        return isset($this->transactions_count) ? $this->transactions_count > 0 : $this->transactions()->exists();
    }

    // Deactivated by an admin: can't sign in or open either panel. Older rows store "Inactive", newer ones "inactive".
    public function isInactive(): bool
    {
        return strcasecmp((string) $this->status, 'inactive') === 0;
    }

    public function bookRequests()
    {
        return $this->hasMany(BookRequest::class, 'requested_by');
    }

    // Librarians and super admins: the people who run the library
    public function scopeStaff(Builder $query): Builder
    {
        return $query->whereIn('role', ['super_admin', 'librarian']);
    }

    // Why this user can't request $book right now, or null when they can. An overdue block wins over "already borrowed",
    // since it's the one the student has to act on.
    public function borrowingBlockedReason(Book $book): ?string
    {
        $overdue = $this->transactions()->overdue()->count();
        $existing = $overdue ? null : $this->transactions()->open()->where('book_id', $book->id)->first();

        if ($existing) {
            return $existing->isPending()
                ? "You've already requested \"{$book->title}\". A librarian will review it soon."
                : "You already have \"{$book->title}\" borrowed.";
        }

        return $this->accountBorrowingBlockedReason($overdue);
    }

    // Blocks that apply to every book (overdue loans, the borrowing limit), so they can be shown before anyone pays a fee.
    // Pass the overdue count if you already have it.
    public function accountBorrowingBlockedReason(?int $overdue = null): ?string
    {
        $overdue ??= $this->transactions()->overdue()->count();

        if ($overdue > 0) {
            return "You have {$overdue} overdue ".str('book')->plural($overdue).'. Return '.($overdue === 1 ? 'it' : 'them').' before borrowing another.';
        }

        // Pending requests count too, so the queue can't be flooded
        if ($this->transactions()->open()->count() >= static::MAX_ACTIVE_LOANS) {
            return 'You can have up to '.static::MAX_ACTIVE_LOANS.' books borrowed or requested at a time. Return one to borrow another.';
        }

        return null;
    }

    // Staff use the admin panel, students the user panel; deactivated accounts get neither (even with a session still open)
    public function canAccessPanel(Panel $panel): bool
    {
        if ($this->isInactive()) {
            return false;
        }

        return match ($panel->getId()) {
            'admin' => in_array($this->role, ['super_admin', 'librarian']),
            'user' => in_array($this->role, ['user', 'student']),
            default => false,
        };
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isLibrarian(): bool
    {
        return $this->role === 'librarian';
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'super_admin' => 'Super Admin',
            'librarian' => 'Librarian',
            default => 'Student',
        };
    }
}
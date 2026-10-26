<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Filament\Models\Contracts\FilamentUser; // 1. Added import
use Filament\Panel; // 2. Added import

// 3. Implemented FilamentUser interface
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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

    // Why this user can't borrow $book right now, or null when they can
    public function borrowingBlockedReason(Book $book): ?string
    {
        $overdue = $this->transactions()->overdue()->count();

        if ($overdue > 0) {
            return "You have {$overdue} overdue ".str('book')->plural($overdue).'. Return '.($overdue === 1 ? 'it' : 'them').' before borrowing another.';
        }

        if ($this->transactions()->active()->where('book_id', $book->id)->exists()) {
            return "You already have \"{$book->title}\" borrowed.";
        }

        if ($this->transactions()->active()->count() >= static::MAX_ACTIVE_LOANS) {
            return 'You can borrow up to '.static::MAX_ACTIVE_LOANS.' books at a time. Return one to borrow another.';
        }

        return null;
    }

    /**
     * 4. Filament access control logic.
     * This acts as the absolute gatekeeper for your panels.
     */
    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            // Allow both super admins and librarians into the admin panel
            return in_array($this->role, ['super_admin', 'librarian']);
        }

        if ($panel->getId() === 'user') {
            // Regular users/students go here
            return in_array($this->role, ['user', 'student']); 
        }

        return false;
    }

    // Add these helper methods right below it for easier checking later!
    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isLibrarian(): bool
    {
        return $this->role === 'librarian';
    }
}
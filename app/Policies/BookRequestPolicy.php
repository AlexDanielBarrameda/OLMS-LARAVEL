<?php

namespace App\Policies;

use App\Models\BookRequest;
use App\Models\User;

// Librarians ask for new titles; super admins decide
class BookRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isLibrarian();
    }

    public function view(User $user, BookRequest $request): bool
    {
        return $user->isSuperAdmin() || $request->requested_by === $user->id;
    }

    public function create(User $user): bool
    {
        return $user->isLibrarian();
    }

    // Librarians can fix their own request until it's been reviewed
    public function update(User $user, BookRequest $request): bool
    {
        return $request->requested_by === $user->id && $request->isPending();
    }

    public function delete(User $user, BookRequest $request): bool
    {
        return $user->isSuperAdmin() || ($request->requested_by === $user->id && $request->isPending());
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function review(User $user, BookRequest $request): bool
    {
        return $user->isSuperAdmin() && $request->isPending();
    }
}

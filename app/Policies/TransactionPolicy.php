<?php

namespace App\Policies;

use App\Models\Transaction;
use App\Models\User;

// Staff manage loans; only super admins can delete them (deleting erases the borrowing record)
class TransactionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isLibrarian();
    }

    public function view(User $user, Transaction $transaction): bool
    {
        return $user->isSuperAdmin() || $user->isLibrarian();
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isLibrarian();
    }

    public function update(User $user, Transaction $transaction): bool
    {
        return $user->isSuperAdmin() || $user->isLibrarian();
    }

    public function delete(User $user, Transaction $transaction): bool
    {
        return $user->isSuperAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    public function restore(User $user, Transaction $transaction): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, Transaction $transaction): bool
    {
        return $user->isSuperAdmin();
    }
}

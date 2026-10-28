<?php

namespace App\Policies;

use App\Models\Book;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class BookPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Both Super Admins and Librarians can see the list of books
        return $user->isSuperAdmin() || $user->isLibrarian();
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Book $book): bool
    {
        // Both can view specific book details
        return $user->isSuperAdmin() || $user->isLibrarian();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Both can add new books to the library
        return $user->isSuperAdmin() || $user->isLibrarian();
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Book $book): bool
    {
        // Both can edit existing books
        return $user->isSuperAdmin() || $user->isLibrarian();
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Book $book): bool
    {
        // ONLY Super Admins, and only books that were never borrowed: deleting one would erase its fees, fines and refunds
        // (the database refuses too). Set a borrowed book's stock to 0 instead.
        return $user->isSuperAdmin() && ! $book->hasTransactions();
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Book $book): bool
    {
        // ONLY Super Admins can restore deleted books
        return $user->isSuperAdmin();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Book $book): bool
    {
        return $this->delete($user, $book);
    }
}
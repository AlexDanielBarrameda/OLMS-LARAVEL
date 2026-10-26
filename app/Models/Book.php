<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    protected $fillable = ['title', 'author', 'isbn', 'category', 'stock', 'status'];

    // Cover from Open Library by ISBN (no API key). default=false makes it 404 instead of a blank image.
    public function coverUrl(): string
    {
        return "https://covers.openlibrary.org/b/isbn/{$this->normalizedIsbn()}-M.jpg?default=false";
    }

    // Google Books cover, used when Open Library has none
    public function fallbackCoverUrl(): string
    {
        return "https://books.google.com/books/content?vid=ISBN{$this->normalizedIsbn()}&printsec=frontcover&img=1&zoom=1";
    }

    protected function normalizedIsbn(): string
    {
        return preg_replace('/[^0-9Xx]/', '', (string) $this->isbn);
    }
}

<?php

namespace App\Models;

use App\Services\LibraryNotifications;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    // At or below this many copies a book counts as low on stock
    public const LOW_STOCK = 1;

    // Lent out at least this many times to count as popular
    public const POPULAR_AFTER = 5;

    protected $fillable = ['title', 'author', 'isbn', 'category', 'stock', 'status'];

    protected static function booted(): void
    {
        // Alert staff whenever stock drops into the low range (to 1, or to 0)
        static::updated(function (Book $book): void {
            if (! $book->wasChanged('stock')) {
                return;
            }

            if ($book->stock <= static::LOW_STOCK && $book->stock < (int) $book->getOriginal('stock')) {
                app(LibraryNotifications::class)->lowStock($book);
            }
        });
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeLowStock(Builder $query): Builder
    {
        return $query->where('stock', '<=', static::LOW_STOCK);
    }

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

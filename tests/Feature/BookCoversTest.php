<?php

use App\Filament\User\Widgets\AvailableBooksWidget;
use App\Filament\User\Widgets\MyBorrowedBooksWidget;
use App\Models\Book;
use App\Models\Transaction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('user'));

    $this->book = Book::create([
        'title' => "Harry Potter and the Philosopher's Stone",
        'author' => 'J. K. Rowling',
        'isbn' => '978-0747532743',
        'category' => 'Fantasy',
        'stock' => 1,
    ]);

    $this->actingAs(User::factory()->create(['role' => 'student']));
});

it('builds cover URLs from the normalized ISBN', function () {
    expect($this->book->coverUrl())
        ->toBe('https://covers.openlibrary.org/b/isbn/9780747532743-M.jpg?default=false')
        ->and($this->book->fallbackCoverUrl())
        ->toBe('https://books.google.com/books/content?vid=ISBN9780747532743&printsec=frontcover&img=1&zoom=1');
});

it('shows a cover with a Google Books fallback on available books', function () {
    Livewire::test(AvailableBooksWidget::class)
        ->assertSeeHtml('src="https://covers.openlibrary.org/b/isbn/9780747532743-M.jpg?default=false"')
        ->assertSeeHtml('vid=ISBN9780747532743')
        ->assertSeeHtml('loading="lazy"');
});

it('shows a cover on borrowed books', function () {
    Transaction::create([
        'user_id' => auth()->id(),
        'book_id' => $this->book->id,
        'borrow_date' => now(),
        'due_date' => now()->addDays(14),
        'fine_status' => 'None',
    ]);

    Livewire::test(MyBorrowedBooksWidget::class)
        ->assertSeeHtml('src="https://covers.openlibrary.org/b/isbn/9780747532743-M.jpg?default=false"');
});

it('shows short borrowed and due dates on borrowed books', function () {
    $this->travelTo('2026-10-24');

    Transaction::create([
        'user_id' => auth()->id(),
        'book_id' => $this->book->id,
        'borrow_date' => '2026-10-24',
        'due_date' => '2027-01-03',
        'fine_status' => 'None',
    ]);

    Livewire::test(MyBorrowedBooksWidget::class)
        ->assertSee('Borrowed Oct 24')
        ->assertSee('Due Jan 3, 2027')
        ->assertDontSee('Borrowed Oct 24, 2026');
});

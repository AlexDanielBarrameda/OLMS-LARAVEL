<?php

use App\Filament\User\Widgets\AvailableBooksWidget;
use App\Models\Book;
use App\Models\Transaction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('user'));
    $this->travelTo('2026-10-24 10:00:00');
    $this->user = User::factory()->create(['role' => 'student']);
    $this->actingAs($this->user);
    $this->book = makeBook('Dune', stock: 2);
});

function makeBook(string $title, int $stock = 1): Book
{
    return Book::create([
        'title' => $title,
        'author' => 'Author',
        'isbn' => (string) fake()->unique()->isbn13(),
        'stock' => $stock,
    ]);
}

function loan(User $user, Book $book, string $dueDate = '2026-11-01', array $attributes = []): Transaction
{
    return Transaction::create([
        'user_id' => $user->id,
        'book_id' => $book->id,
        'borrow_date' => '2026-10-01',
        'due_date' => $dueDate,
        'fine_status' => 'None',
        ...$attributes,
    ]);
}

function expectBlocked(string $message): void
{
    $before = Transaction::count();
    $stock = test()->book->fresh()->stock;

    Livewire::test(AvailableBooksWidget::class)
        ->callTableAction('borrow', test()->book)
        ->assertNotified("Can't borrow this book");

    expect(Transaction::count())->toBe($before)
        ->and(test()->book->fresh()->stock)->toBe($stock)
        ->and(test()->user->borrowingBlockedReason(test()->book))->toContain($message);
}

it('lets a user in good standing borrow a book', function () {
    Livewire::test(AvailableBooksWidget::class)
        ->callTableAction('borrow', $this->book)
        ->assertNotified('Book Borrowed!');

    expect($this->user->transactions()->active()->where('book_id', $this->book->id)->exists())->toBeTrue()
        ->and($this->book->fresh()->stock)->toBe(1);
});

it('blocks borrowing while the user has an overdue book', function () {
    loan($this->user, makeBook('Late Book'), dueDate: '2026-10-20');

    expectBlocked('You have 1 overdue book.');
});

it('blocks borrowing past the limit of 3 active books', function () {
    foreach (['One', 'Two', 'Three'] as $title) {
        loan($this->user, makeBook($title));
    }

    expectBlocked('up to 3 books at a time');
});

it('blocks borrowing a second copy of a book the user already has', function () {
    loan($this->user, $this->book);

    expectBlocked('You already have "Dune" borrowed.');
});

it('does not count returned books against the rules', function () {
    loan($this->user, $this->book, dueDate: '2026-10-01', attributes: ['fine_status' => 'Returned']);
    loan($this->user, makeBook('Two'), dueDate: '2026-09-01', attributes: ['return_date' => '2026-09-01']);
    loan($this->user, makeBook('Three'));
    loan($this->user, makeBook('Four'));

    expect($this->user->borrowingBlockedReason($this->book))->toBeNull();
});

it('marks a returned book with a return date so it stops counting', function () {
    $loan = loan($this->user, $this->book);

    Livewire::test(\App\Filament\User\Widgets\MyBorrowedBooksWidget::class)
        ->callTableAction('return', $loan);

    expect($loan->fresh())
        ->fine_status->toBe('Returned')
        ->return_date->not->toBeNull()
        ->and($loan->fresh()->isActive())->toBeFalse();
});

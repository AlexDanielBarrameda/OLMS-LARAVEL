<?php

use App\Enums\TransactionStatus;
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
        'status' => TransactionStatus::Approved,
        'borrow_date' => '2026-10-01',
        'due_date' => $dueDate,
        'fine_status' => 'None',
        ...$attributes,
    ]);
}

function expectBlocked(string $message): void
{
    $before = Transaction::count();

    Livewire::test(AvailableBooksWidget::class)
        ->callTableAction('borrow', test()->book)
        ->assertNotified("Can't request this book");

    expect(Transaction::count())->toBe($before)
        ->and(test()->user->borrowingBlockedReason(test()->book))->toContain($message);
}

it('sends a borrow request without taking stock, and tells the librarians', function () {
    $librarian = User::factory()->create(['role' => 'librarian']);

    Livewire::test(AvailableBooksWidget::class)
        ->callTableAction('borrow', $this->book)
        ->assertNotified('Request sent');

    $request = $this->user->transactions()->sole();

    expect($request->status)->toBe(TransactionStatus::Pending)
        ->and($this->book->fresh()->stock)->toBe(2)
        ->and($librarian->notifications()->first()->data['title'])->toBe('New borrow request');
});

it('labels the button by what the user already has', function () {
    loan($this->user, $this->book, attributes: ['status' => TransactionStatus::Pending]);

    Livewire::test(AvailableBooksWidget::class)
        ->assertTableActionHasLabel('borrow', 'Requested', $this->book)
        ->assertTableActionDisabled('borrow', $this->book)
        ->assertTableActionHasLabel('borrow', 'Request to Borrow', makeBook('Emma'));
});

it('blocks requests while the user has an overdue book', function () {
    loan($this->user, makeBook('Late Book'), dueDate: '2026-10-20');

    expectBlocked('You have 1 overdue book.');
});

it('counts pending requests toward the limit of 3', function () {
    loan($this->user, makeBook('One'));
    loan($this->user, makeBook('Two'));
    loan($this->user, makeBook('Three'), attributes: ['status' => TransactionStatus::Pending]);

    expectBlocked('up to 3 books borrowed or requested');
});

it('blocks a second request for a book the user already has or has requested', function () {
    $loan = loan($this->user, $this->book);

    expect($this->user->borrowingBlockedReason($this->book))->toBe('You already have "Dune" borrowed.');

    $loan->update(['status' => TransactionStatus::Pending]);

    expect($this->user->borrowingBlockedReason($this->book))->toContain('already requested');
});

it('does not count returned or rejected loans against the rules', function () {
    loan($this->user, $this->book, dueDate: '2026-10-01', attributes: ['status' => TransactionStatus::Returned]);
    loan($this->user, makeBook('Two'), dueDate: '2026-09-01', attributes: ['return_date' => '2026-09-01']);
    loan($this->user, makeBook('Three'), attributes: ['status' => TransactionStatus::Rejected]);
    loan($this->user, makeBook('Four'));
    loan($this->user, makeBook('Five'));

    expect($this->user->borrowingBlockedReason($this->book))->toBeNull();
});

it('returns a book: sets the return date and puts the copy back', function () {
    $loan = loan($this->user, $this->book);

    expect($this->book->fresh()->stock)->toBe(1);

    Livewire::test(MyBorrowedBooksWidget::class)
        ->callTableAction('return', $loan);

    expect($loan->fresh())
        ->status->toBe(TransactionStatus::Returned)
        ->return_date->not->toBeNull()
        ->and($this->book->fresh()->stock)->toBe(2);
});

it('lets the user cancel a pending request', function () {
    $request = loan($this->user, $this->book, attributes: ['status' => TransactionStatus::Pending]);

    Livewire::test(MyBorrowedBooksWidget::class)
        ->assertSee('Awaiting approval')
        ->assertTableActionHidden('return', $request)
        ->callTableAction('cancelRequest', $request);

    expect(Transaction::find($request->id))->toBeNull()
        ->and($this->book->fresh()->stock)->toBe(2);
});

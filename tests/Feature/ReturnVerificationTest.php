<?php

use App\Enums\TransactionStatus;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Models\Book;
use App\Models\Transaction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->travelTo('2026-10-29 10:00:00');

    $this->librarian = User::factory()->create(['role' => 'librarian']);
    $this->student = User::factory()->create(['role' => 'student']);
    $this->book = Book::create(['title' => 'Dune', 'author' => 'Frank Herbert', 'isbn' => '9780441013593', 'stock' => 2]);

    // On loan (takes a copy), then handed in by the student
    $this->loan = Transaction::create([
        'user_id' => $this->student->id, 'book_id' => $this->book->id, 'status' => TransactionStatus::Approved,
        'borrow_date' => '2026-10-20', 'due_date' => '2026-11-03', 'fine_status' => 'None',
    ]);
    $this->loan->requestReturn();

    $this->actingAs($this->librarian);
});

it('keeps the copy off the shelf until staff check the book in', function () {
    expect($this->book->fresh()->stock)->toBe(1)
        ->and(Transaction::checkedOut()->count())->toBe(1);

    Livewire::test(ListTransactions::class)
        ->filterTable('returns')
        ->assertCanSeeTableRecords([$this->loan])
        ->assertSee('Return to verify · since Oct 29');

    $this->get(\App\Filament\Pages\AdminDashboard::getUrl())->assertSee('Check in 1 return');
});

it('lets staff verify the return: back on the shelf and off the student\'s account', function () {
    Livewire::test(ListTransactions::class)
        ->callTableAction('verifyReturn', $this->loan)
        ->assertNotified('Book checked in');

    expect($this->loan->fresh())
        ->status->toBe(TransactionStatus::Returned)
        ->return_date->toBe('2026-10-29')
        ->and($this->book->fresh()->stock)->toBe(2)
        ->and($this->student->transactions()->open()->count())->toBe(0)
        ->and($this->student->notifications()->first()->data['title'])->toBe('"Dune" is returned');
});

it('keeps the book on loan when staff never received it', function () {
    Livewire::test(ListTransactions::class)
        ->callTableAction('rejectReturn', $this->loan, data: ['reason' => 'It wasn\'t in the return box.'])
        ->assertNotified('Kept on loan');

    expect($this->loan->fresh())
        ->status->toBe(TransactionStatus::Approved)
        ->return_requested_at->toBeNull()
        ->and($this->book->fresh()->stock)->toBe(1)
        ->and($this->student->notifications()->first()->data['title'])->toBe('We haven\'t received "Dune"');
});

it('keeps an unconfirmed return overdue once its due date passes, so claiming a return can\'t lift the block', function () {
    $this->travelTo('2026-11-05 10:00:00');

    expect($this->loan->fresh()->isOverdue())->toBeTrue()
        ->and(Transaction::overdue()->pluck('id')->all())->toBe([$this->loan->id])
        ->and($this->student->accountBorrowingBlockedReason())->toContain('You have 1 overdue book.');

    // Staff are warned that a late book shouldn't be checked in without its fine
    Livewire::test(ListTransactions::class)
        ->mountTableAction('verifyReturn', $this->loan)
        ->assertMountedActionModalSee('2 days overdue');

    // The student can't switch to the fine flow from here; staff decide first
    Filament::setCurrentPanel(Filament::getPanel('user'));
    $this->actingAs($this->student);

    Livewire::test(\App\Filament\User\Widgets\MyBorrowedBooksWidget::class)
        ->assertSee('Awaiting check-in · overdue')
        ->assertTableActionHidden('returnOverdue', $this->loan);
});

it('only offers the return actions on returns waiting to be checked in', function () {
    $this->loan->confirmReturn();

    Livewire::test(ListTransactions::class)
        ->assertTableActionHidden('verifyReturn', $this->loan)
        ->assertTableActionHidden('rejectReturn', $this->loan);
});

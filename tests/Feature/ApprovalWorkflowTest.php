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
    $this->travelTo('2026-10-26 09:00:00');
    $this->librarian = User::factory()->create(['role' => 'librarian']);
    $this->actingAs($this->librarian);

    $this->student = User::factory()->create(['role' => 'student']);
    $this->book = Book::create(['title' => 'Dune', 'author' => 'Frank Herbert', 'isbn' => '9780441013593', 'stock' => 2]);
    $this->request = Transaction::create([
        'user_id' => $this->student->id,
        'book_id' => $this->book->id,
        'status' => TransactionStatus::Pending,
        'borrow_date' => '2026-10-20',
        'due_date' => '2026-11-03',
        'fine_status' => 'None',
    ]);
});

it('approves a request: takes a copy, starts the loan today, and tells the student', function () {
    Livewire::test(ListTransactions::class)
        ->callTableAction('approve', $this->request)
        ->assertNotified('Request approved');

    expect($this->request->fresh())
        ->status->toBe(TransactionStatus::Approved)
        ->borrow_date->toBe('2026-10-26')
        ->due_date->toBe('2026-11-09')
        ->and($this->book->fresh()->stock)->toBe(1)
        ->and($this->student->notifications()->first()->data['title'])->toBe('Your request for "Dune" was approved');
});

it("won't approve when the book is out of stock", function () {
    $this->book->update(['stock' => 0]);

    Livewire::test(ListTransactions::class)
        ->callTableAction('approve', $this->request)
        ->assertNotified("Can't approve this request");

    expect($this->request->fresh()->status)->toBe(TransactionStatus::Pending)
        ->and($this->book->fresh()->stock)->toBe(0);
});

it('rejects a request with a reason, without touching stock', function () {
    Livewire::test(ListTransactions::class)
        ->callTableAction('reject', $this->request, data: ['reason' => 'Reserved for a class this week.'])
        ->assertNotified('Request rejected');

    $notification = $this->student->notifications()->first()->data;

    expect($this->request->fresh()->status)->toBe(TransactionStatus::Rejected)
        ->and($this->book->fresh()->stock)->toBe(2)
        ->and($notification['title'])->toBe('Your request for "Dune" was declined')
        ->and($notification['body'])->toBe('Reserved for a class this week.');
});

it('only offers approve and reject on pending requests', function () {
    $this->request->approve();

    Livewire::test(ListTransactions::class)
        ->assertTableActionHidden('approve', $this->request)
        ->assertTableActionHidden('reject', $this->request);
});

it('lists pending requests first and filters to them', function () {
    $loan = Transaction::create([
        'user_id' => $this->student->id,
        'book_id' => Book::create(['title' => 'Emma', 'author' => 'Austen', 'isbn' => '9780141439587', 'stock' => 3])->id,
        'status' => TransactionStatus::Approved,
        'borrow_date' => '2026-10-01',
        'due_date' => '2026-10-10',
        'fine_status' => 'None',
    ]);

    Livewire::test(ListTransactions::class)
        ->assertCanSeeTableRecords([$this->request, $loan], inOrder: true)
        ->assertSee('Awaiting approval')
        ->filterTable('pending')
        ->assertCanSeeTableRecords([$this->request])
        ->assertCanNotSeeTableRecords([$loan]);
});

it('keeps stock right however a loan changes', function () {
    $this->request->approve();
    expect($this->book->fresh()->stock)->toBe(1);

    // Staff setting a return date in the edit form closes the loan and returns the copy
    $this->request->update(['return_date' => '2026-10-28']);
    expect($this->request->fresh()->status)->toBe(TransactionStatus::Returned)
        ->and($this->book->fresh()->stock)->toBe(2);

    // A desk loan created as approved takes a copy; deleting it gives it back
    $desk = Transaction::create([
        'user_id' => $this->student->id,
        'book_id' => $this->book->id,
        'status' => TransactionStatus::Approved,
        'borrow_date' => '2026-10-26',
        'due_date' => '2026-11-09',
        'fine_status' => 'None',
    ]);
    expect($this->book->fresh()->stock)->toBe(1);

    $desk->delete();
    expect($this->book->fresh()->stock)->toBe(2);
});

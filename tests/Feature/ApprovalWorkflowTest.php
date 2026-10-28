<?php

use App\Enums\TransactionStatus;
use App\Filament\Resources\Transactions\Pages\EditTransaction;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Filament\User\Widgets\MyBorrowedBooksWidget;
use App\Services\LibraryNotifications;
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

it('lends for the paid-for duration, starting on approval', function () {
    $this->request->update(['duration_days' => 28, 'fee_amount' => 60, 'gcash_reference' => '1012345678901']);

    Livewire::test(ListTransactions::class)
        ->assertSee('Awaiting approval · 28 days · ₱60.00')
        ->callTableAction('approve', $this->request, data: ['payment_found' => true])
        ->assertHasNoTableActionErrors();

    expect($this->request->fresh())
        ->borrow_date->toBe('2026-10-26')
        ->due_date->toBe('2026-11-23')
        ->payment_verified_by->toBe($this->librarian->id)
        ->payment_verified_at->not->toBeNull();

    // Shown to staff in the approve modal
    $html = view('filament.admin.borrow-request', ['loan' => $this->request->fresh(), 'receiptUrl' => 'https://example.test/r.png'])->render();
    expect($html)->toContain('28 days', '₱60.00', '1012345678901', 'View receipt');
});

it("won't approve a paid request until staff confirm the payment arrived", function () {
    $this->request->update(['duration_days' => 7, 'fee_amount' => 15, 'gcash_reference' => '1012345678901']);

    Livewire::test(ListTransactions::class)
        ->callTableAction('approve', $this->request, data: ['payment_found' => false])
        ->assertHasTableActionErrors(['payment_found' => 'accepted']);

    expect($this->request->fresh())
        ->status->toBe(TransactionStatus::Pending)
        ->payment_verified_at->toBeNull();
});

it('refunds a rejected paid request only if staff found the payment', function () {
    $this->request->update(['duration_days' => 7, 'fee_amount' => 15, 'gcash_reference' => '1012345678901']);

    Livewire::test(ListTransactions::class)
        ->callTableAction('reject', $this->request, data: ['reason' => 'Book is reserved.', 'payment_found' => true]);

    expect($this->request->fresh()->refund_status)->toBe(Transaction::REFUND_PENDING)
        ->and($this->student->notifications()->first()->data['body'])
        ->toBe('Book is reserved. Your ₱15.00 fee will be refunded via GCash.');
});

it('leaves an unchecked payment waiting for a check when a paid request is rejected', function () {
    $this->request->update(['duration_days' => 7, 'fee_amount' => 15, 'gcash_reference' => '1012345678901']);

    Livewire::test(ListTransactions::class)
        ->callTableAction('reject', $this->request, data: ['reason' => 'Book is reserved.']);

    expect($this->request->fresh())
        ->refund_status->toBeNull()
        ->needsPaymentCheck()->toBeTrue()
        ->and($this->student->notifications()->first()->data['body'])
        ->toBe('Book is reserved. The library will refund your ₱15.00 once staff confirm your GCash payment.');
});

it("won't reject a request someone already approved", function () {
    $form = Livewire::test(ListTransactions::class)->mountTableAction('reject', $this->request);

    // Another librarian approves it while this one's modal is open
    $this->request->approve($this->librarian);

    $form->callMountedTableAction();

    expect($this->request->fresh()->status)->toBe(TransactionStatus::Approved)
        ->and($this->book->fresh()->stock)->toBe(1);
});

it('only changes status through the table actions, not the edit form', function () {
    $this->request->update(['duration_days' => 7, 'fee_amount' => 15, 'gcash_reference' => '1012345678901']);

    Livewire::test(EditTransaction::class, ['record' => $this->request->getRouteKey()])
        ->assertFormFieldIsDisabled('status')
        ->assertFormFieldIsDisabled('return_date')
        // Even if the browser sends a new status, it isn't saved
        ->set('data.status', TransactionStatus::Approved->value)
        ->set('data.return_date', '2026-10-26')
        ->call('save')
        ->assertHasNoFormErrors();

    expect($this->request->fresh())
        ->status->toBe(TransactionStatus::Pending)
        ->return_date->toBeNull()
        ->payment_verified_at->toBeNull()
        ->and($this->book->fresh()->stock)->toBe(2);
});

it('saves a loan created at the desk as on loan', function () {
    Livewire::test(\App\Filament\Resources\Transactions\Pages\CreateTransaction::class)
        ->fillForm(['user_id' => $this->student->id, 'book_id' => $this->book->id, 'status' => TransactionStatus::Returned->value])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Transaction::latest('id')->first()->status)->toBe(TransactionStatus::Approved)
        ->and($this->book->fresh()->stock)->toBe(1);
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
    $this->request->approve($this->librarian);

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

it('hides every delete action from librarians, but not from super admins', function () {
    Livewire::test(ListTransactions::class)
        ->assertTableActionHidden('delete', $this->request)
        ->assertTableBulkActionHidden('delete');

    Livewire::test(EditTransaction::class, ['record' => $this->request->getRouteKey()])
        ->assertActionHidden('delete');

    expect($this->librarian->can('delete', $this->request))->toBeFalse();

    $this->actingAs(User::factory()->create(['role' => 'super_admin']));

    Livewire::test(ListTransactions::class)
        ->assertTableActionVisible('delete', $this->request)
        ->assertTableBulkActionVisible('delete');
});

it('takes down the request alert and tells staff when a student cancels', function () {
    app(LibraryNotifications::class)->borrowRequested($this->request->load('user', 'book'));
    // A pre-existing alert from before alerts were tagged with their transaction
    $this->librarian->notifications()->create([
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'type' => 'Filament\\Notifications\\DatabaseNotification',
        'data' => ['title' => 'New borrow request', 'body' => "{$this->student->name} wants to borrow \"Dune\".", 'format' => 'filament'],
    ]);

    expect($this->librarian->notifications()->count())->toBe(2);

    Filament::setCurrentPanel(Filament::getPanel('user'));
    $this->actingAs($this->student);

    Livewire::test(MyBorrowedBooksWidget::class)
        ->callTableAction('cancelRequest', $this->request);

    $titles = $this->librarian->fresh()->notifications()->pluck('data')->pluck('title')->all();

    expect($titles)->toBe(['Borrow request cancelled'])
        ->and($this->librarian->notifications()->first()->data['body'])->toBe("{$this->student->name} cancelled their request for \"Dune\".");
});

it('marks the request alert read for everyone once a librarian decides', function () {
    $otherLibrarian = User::factory()->create(['role' => 'librarian']);
    app(LibraryNotifications::class)->borrowRequested($this->request->load('user', 'book'));

    Livewire::test(ListTransactions::class)->callTableAction('approve', $this->request);

    // (Approving also drops Dune to its last copy, which raises a separate low-stock alert)
    $requestAlert = $otherLibrarian->notifications()->get()->firstWhere('data.title', 'New borrow request');

    expect($requestAlert->read_at)->not->toBeNull();
});

it('keeps stock right however a loan changes', function () {
    $this->request->approve($this->librarian);
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

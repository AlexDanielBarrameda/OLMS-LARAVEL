<?php

use App\Enums\TransactionStatus;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Filament\User\Widgets\MyBorrowedBooksWidget;
use App\Models\Book;
use App\Models\Transaction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('receipts', ['serve' => true]);
    $this->travelTo('2026-10-29 10:00:00');

    $this->student = User::factory()->create(['role' => 'student']);
    $this->librarian = User::factory()->create(['role' => 'librarian']);
    $this->book = Book::create(['title' => 'Dune', 'author' => 'Frank Herbert', 'isbn' => '9780441013593', 'stock' => 3]);

    // 5 days overdue; creating it as approved takes a copy (stock 3 → 2)
    $this->loan = Transaction::create([
        'user_id' => $this->student->id, 'book_id' => $this->book->id, 'status' => TransactionStatus::Approved,
        'borrow_date' => '2026-10-10', 'due_date' => '2026-10-24', 'fine_status' => 'None',
    ]);
});

function actAsStudent(): void
{
    Filament::setCurrentPanel(Filament::getPanel('user'));
    test()->actingAs(test()->student);
}

function actAsLibrarian(): void
{
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    test()->actingAs(test()->librarian);
}

function submitReceipt(): void
{
    actAsStudent();

    Livewire::test(MyBorrowedBooksWidget::class)
        ->callTableAction('returnOverdue', test()->loan, data: [
            'receipt' => UploadedFile::fake()->image('gcash-receipt.png', 400, 800),
        ])
        ->assertHasNoTableActionErrors();
}

it('charges ₱5 a day: 5 days late is ₱25.00', function () {
    expect($this->loan->daysOverdue())->toBe(5)
        ->and($this->loan->fineDue())->toBe(25.0)
        ->and(Transaction::peso($this->loan->fineDue()))->toBe('₱25.00');
});

it('uses the plain confirmation for books that are not overdue', function () {
    $this->loan->update(['due_date' => '2026-11-05']);
    actAsStudent();

    Livewire::test(MyBorrowedBooksWidget::class)
        ->assertTableActionVisible('return', $this->loan)
        ->assertTableActionHidden('returnOverdue', $this->loan);
});

it('routes overdue books to the GCash fine form', function () {
    actAsStudent();

    Livewire::test(MyBorrowedBooksWidget::class)
        ->assertTableActionHidden('return', $this->loan)
        ->assertTableActionVisible('returnOverdue', $this->loan)
        ->mountTableAction('returnOverdue', $this->loan)
        ->assertHasNoTableActionErrors();

    // The fine + QR block inside that modal
    $html = view('filament.user.fine-payment', [
        'days' => $this->loan->daysOverdue(),
        'rate' => Transaction::peso(Transaction::FINE_PER_DAY),
        'amount' => Transaction::peso($this->loan->fineDue()),
        'qrUrl' => asset('images/gcash-qr.png'),
    ])->render();

    expect($html)->toContain('₱25.00', '5 days late × ₱5.00 per day', 'images/gcash-qr.png');
});

it('requires a receipt', function () {
    actAsStudent();

    Livewire::test(MyBorrowedBooksWidget::class)
        ->callTableAction('returnOverdue', $this->loan, data: ['receipt' => null])
        ->assertHasTableActionErrors(['receipt' => 'required']);

    expect($this->loan->fresh()->status)->toBe(TransactionStatus::Approved);
});

it('submits the payment for review without returning the book', function () {
    submitReceipt();

    $loan = $this->loan->fresh();

    expect($loan->status)->toBe(TransactionStatus::PendingFineVerification)
        ->and($loan->fine_amount)->toBe('25.00')
        ->and($loan->fine_status)->toBe('Pending')
        ->and($loan->return_date)->toBeNull()
        ->and($this->book->fresh()->stock)->toBe(2) // still checked out
        ->and($this->librarian->notifications()->first()->data['title'])->toBe('Fine payment to verify');

    Storage::disk('receipts')->assertExists($loan->payment_receipt_path);
});

it('locks in the fine when submitted, and stops overdue reminders', function () {
    submitReceipt();
    $this->travel(3)->days();

    expect($this->loan->fresh()->fineDue())->toBe(25.0)
        ->and(Transaction::needsReminder()->count())->toBe(0)
        ->and(Transaction::overdue()->count())->toBe(0)
        ->and(Transaction::checkedOut()->count())->toBe(1);
});

it('lets staff verify the payment, which returns the book', function () {
    submitReceipt();
    actAsLibrarian();

    Livewire::test(ListTransactions::class)
        ->assertTableActionVisible('verifyPayment', $this->loan)
        ->assertSee('Fine under review · ₱25.00')
        ->callTableAction('verifyPayment', $this->loan)
        ->assertNotified('Payment verified and book returned');

    $loan = $this->loan->fresh();

    expect($loan->status)->toBe(TransactionStatus::Returned)
        ->and($loan->fine_status)->toBe('Paid')
        ->and($loan->return_date)->not->toBeNull()
        ->and($this->book->fresh()->stock)->toBe(3)
        ->and($this->student->notifications()->first()->data['title'])->toBe('"Dune" is returned')
        ->and($this->librarian->unreadNotifications()->where('data->title', 'Fine payment to verify')->count())->toBe(0);
});

it('lets staff reject a bad receipt, sending the loan back to overdue', function () {
    submitReceipt();
    actAsLibrarian();

    Livewire::test(ListTransactions::class)
        ->callTableAction('rejectPayment', $this->loan, data: ['reason' => 'The amount paid doesn\'t match the fine.']);

    $loan = $this->loan->fresh();

    expect($loan->status)->toBe(TransactionStatus::Approved)
        ->and($loan->isOverdue())->toBeTrue()
        ->and($loan->fine_amount)->toBeNull()
        ->and($this->book->fresh()->stock)->toBe(2)
        ->and($this->student->notifications()->first()->data['body'])->toContain('The amount paid doesn\'t match the fine.');
});

it('shows the fine review on the dashboard and filters to it', function () {
    submitReceipt();
    actAsLibrarian();

    Livewire::test(ListTransactions::class)
        ->filterTable('fine_review')
        ->assertCanSeeTableRecords([$this->loan]);

    $this->get(\App\Filament\Pages\AdminDashboard::getUrl())->assertSee('Verify 1 fine payment');
});

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
    $this->book = Book::create(['title' => 'Dune', 'author' => 'Frank Herbert', 'isbn' => '9780441013593', 'stock' => 2]);

    // A paid 14-day request whose payment staff have already found in GCash
    Storage::disk('receipts')->put('borrow/paid.png', 'receipt');
    $this->request = Transaction::create([
        'user_id' => $this->student->id, 'book_id' => $this->book->id, 'status' => TransactionStatus::Pending,
        'borrow_date' => '2026-10-29', 'due_date' => '2026-11-12', 'duration_days' => 14, 'fee_amount' => 30,
        'gcash_reference' => '1012345678901', 'payment_verified_at' => now(), 'payment_verified_by' => $this->librarian->id,
        'borrow_receipt_path' => 'borrow/paid.png', 'fine_status' => 'None',
    ]);
});

// The same request, before anyone has checked the payment: all the school has is the student's screenshot
function unverified(): Transaction
{
    test()->request->update(['payment_verified_at' => null, 'payment_verified_by' => null]);

    return test()->request;
}

function asStaff(): void
{
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    test()->actingAs(test()->librarian);
}

it('owes a refund when a paid request is rejected or cancelled, but not when approved', function () {
    $this->request->update(['status' => TransactionStatus::Rejected]);
    expect($this->request->fresh()->refund_status)->toBe(Transaction::REFUND_PENDING);

    $other = $this->request->replicate()->fill(['status' => TransactionStatus::Pending, 'refund_status' => null, 'gcash_reference' => '2012345678901']);
    $other->save();
    $other->approve($this->librarian);
    expect($other->fresh()->refund_status)->toBeNull();
});

it('owes a refund when the student cancels a paid request, and tells staff', function () {
    Filament::setCurrentPanel(Filament::getPanel('user'));
    $this->actingAs($this->student);

    Livewire::test(MyBorrowedBooksWidget::class)->callTableAction('cancelRequest', $this->request);

    expect($this->request->fresh())
        ->status->toBe(TransactionStatus::Cancelled)
        ->refund_status->toBe(Transaction::REFUND_PENDING)
        ->and($this->librarian->notifications()->first()->data['body'])->toContain('Refund their ₱30.00 fee');
});

it('lets staff process a refund with a screenshot, then shows it as refunded', function () {
    $this->request->update(['status' => TransactionStatus::Rejected]);
    asStaff();

    Livewire::test(ListTransactions::class)
        ->assertSee('Refund pending · ₱30.00')
        ->assertTableActionVisible('processRefund', $this->request)
        ->assertTableActionHidden('viewRefundReceipt', $this->request)
        ->callTableAction('processRefund', $this->request, data: [
            'refund_receipt' => UploadedFile::fake()->image('refund.png', 400, 800),
        ])
        ->assertHasNoTableActionErrors()
        ->assertNotified('Refund recorded');

    $loan = $this->request->fresh();

    expect($loan->refund_status)->toBe(Transaction::REFUNDED)
        ->and($loan->refunded_at)->not->toBeNull()
        ->and($this->student->notifications()->first()->data['title'])->toBe('Your ₱30.00 refund was sent');

    Storage::disk('receipts')->assertExists($loan->refund_receipt_path);

    Livewire::test(ListTransactions::class)
        ->assertSee('Refunded')
        ->assertTableActionHidden('processRefund', $loan)
        ->assertTableActionVisible('viewRefundReceipt', $loan);
});

it('requires the refund screenshot', function () {
    $this->request->update(['status' => TransactionStatus::Rejected]);
    asStaff();

    Livewire::test(ListTransactions::class)
        ->callTableAction('processRefund', $this->request, data: ['refund_receipt' => null])
        ->assertHasTableActionErrors(['refund_receipt' => 'required']);

    expect($this->request->fresh()->refund_status)->toBe(Transaction::REFUND_PENDING);
});

it('does not offer refunds for free or still-active requests', function () {
    $free = Transaction::create([
        'user_id' => $this->student->id, 'book_id' => $this->book->id, 'status' => TransactionStatus::Rejected,
        'borrow_date' => '2026-10-29', 'due_date' => '2026-11-12', 'fine_status' => 'None',
    ]);
    asStaff();

    Livewire::test(ListTransactions::class)
        ->assertTableActionHidden('processRefund', $free)
        ->assertTableActionHidden('processRefund', $this->request);
});

it('never queues a refund for a payment nobody has confirmed, however the request ends', function () {
    unverified()->update(['status' => TransactionStatus::Rejected]);
    expect($this->request->fresh())->refund_status->toBeNull()->needsPaymentCheck()->toBeTrue();

    $refused = Transaction::create([...$this->request->only(['user_id', 'book_id', 'borrow_date', 'due_date', 'duration_days', 'fee_amount']), 'status' => TransactionStatus::Cancelled, 'gcash_reference' => '2012345678901']);
    expect($refused->fresh()->refund_status)->toBeNull();

    asStaff();
    Livewire::test(ListTransactions::class)
        ->assertSee('Check payment · ₱30.00')
        ->assertTableActionHidden('processRefund', $this->request)
        ->assertTableActionVisible('checkPayment', $this->request);
});

it('asks staff to check the payment when a student cancels an unconfirmed paid request', function () {
    unverified();
    Filament::setCurrentPanel(Filament::getPanel('user'));
    $this->actingAs($this->student);

    Livewire::test(MyBorrowedBooksWidget::class)->callTableAction('cancelRequest', $this->request);

    $alert = $this->librarian->notifications()->first()->data;

    expect($this->request->fresh())
        ->status->toBe(TransactionStatus::Cancelled)
        ->refund_status->toBeNull()
        ->and($alert['body'])->toContain('Check that their ₱30.00 payment (ref 1012345678901)')
        ->and($alert['actions'][0]['label'])->toBe('Check payment');
});

it('queues the refund once staff find the payment in GCash', function () {
    unverified()->update(['status' => TransactionStatus::Cancelled]);
    asStaff();

    Livewire::test(ListTransactions::class)
        ->callTableAction('checkPayment', $this->request, data: ['outcome' => 'received'])
        ->assertHasNoTableActionErrors()
        ->assertNotified('Payment confirmed');

    expect($this->request->fresh())
        ->refund_status->toBe(Transaction::REFUND_PENDING)
        ->payment_verified_by->toBe($this->librarian->id);

    Livewire::test(ListTransactions::class)
        ->assertTableActionVisible('processRefund', $this->request)
        ->assertTableActionHidden('checkPayment', $this->request);
});

it('closes the request without a refund when the payment never arrived, and tells the student', function () {
    unverified()->update(['status' => TransactionStatus::Cancelled]);
    asStaff();

    Livewire::test(ListTransactions::class)
        ->callTableAction('checkPayment', $this->request, data: ['outcome' => 'not_found'])
        ->assertNotified('Marked as not paid')
        ->assertSee('Payment not found');

    expect($this->request->fresh())
        ->refund_status->toBe(Transaction::REFUND_NOT_PAID)
        ->payment_verified_at->toBeNull()
        ->and($this->student->notifications()->first()->data['title'])->toBe('We couldn\'t find your payment');

    Livewire::test(ListTransactions::class)
        ->assertTableActionHidden('processRefund', $this->request)
        ->assertTableActionHidden('checkPayment', $this->request);
});

it('lists payments to check and links to them from the dashboard', function () {
    unverified()->update(['status' => TransactionStatus::Rejected]);
    asStaff();

    Livewire::test(ListTransactions::class)
        ->filterTable('payment_checks')
        ->assertCanSeeTableRecords([$this->request]);

    $this->get(\App\Filament\Pages\AdminDashboard::getUrl())->assertSee('Check 1 payment');
});

it('lists refunds to process and links to them from the dashboard', function () {
    $this->request->update(['status' => TransactionStatus::Cancelled]);
    asStaff();

    Livewire::test(ListTransactions::class)
        ->filterTable('refunds')
        ->assertCanSeeTableRecords([$this->request]);

    $this->get(\App\Filament\Pages\AdminDashboard::getUrl())->assertSee('Process 1 refund');
});

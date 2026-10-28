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

    // A paid 14-day request
    Storage::disk('receipts')->put('borrow/paid.png', 'receipt');
    $this->request = Transaction::create([
        'user_id' => $this->student->id, 'book_id' => $this->book->id, 'status' => TransactionStatus::Pending,
        'borrow_date' => '2026-10-29', 'due_date' => '2026-11-12', 'duration_days' => 14, 'fee_amount' => 30,
        'borrow_receipt_path' => 'borrow/paid.png', 'fine_status' => 'None',
    ]);
});

function asStaff(): void
{
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    test()->actingAs(test()->librarian);
}

it('owes a refund when a paid request is rejected or cancelled, but not when approved', function () {
    $this->request->update(['status' => TransactionStatus::Rejected]);
    expect($this->request->fresh()->refund_status)->toBe(Transaction::REFUND_PENDING);

    $other = $this->request->replicate()->fill(['status' => TransactionStatus::Pending, 'refund_status' => null]);
    $other->save();
    $other->approve();
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

it('lists refunds to process and links to them from the dashboard', function () {
    $this->request->update(['status' => TransactionStatus::Cancelled]);
    asStaff();

    Livewire::test(ListTransactions::class)
        ->filterTable('refunds')
        ->assertCanSeeTableRecords([$this->request]);

    $this->get(\App\Filament\Pages\AdminDashboard::getUrl())->assertSee('Process 1 refund');
});

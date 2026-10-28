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

// Blocked students can't even open the paid request form, so they never pay for a request that would be refused
function expectBlocked(string $message): void
{
    Livewire::test(AvailableBooksWidget::class)
        ->assertTableActionDisabled('borrow', test()->book);

    expect(test()->user->accountBorrowingBlockedReason())->toContain($message)
        ->and(test()->user->borrowingBlockedReason(test()->book))->toContain($message);
}

function requestBorrow(array $data): \Livewire\Features\SupportTesting\Testable
{
    return Livewire::test(AvailableBooksWidget::class)
        ->callTableAction('borrow', test()->book, data: [
            'gcash_reference' => (string) fake()->unique()->numerify('#############'),
            'receipt' => \Illuminate\Http\UploadedFile::fake()->image('gcash.png', 400, 800),
            ...$data,
        ]);
}

it('sends a paid borrow request without taking stock, and tells the librarians', function () {
    \Illuminate\Support\Facades\Storage::fake('receipts', ['serve' => true]);
    $librarian = User::factory()->create(['role' => 'librarian']);

    requestBorrow(['duration' => '14', 'gcash_reference' => '1012345678901'])
        ->assertHasNoTableActionErrors()
        ->assertNotified('Request sent');

    $request = $this->user->transactions()->sole();

    expect($request->status)->toBe(TransactionStatus::Pending)
        ->and($request->gcash_reference)->toBe('1012345678901')
        ->and($request->payment_verified_at)->toBeNull()
        ->and($request->duration_days)->toBe(14)
        ->and($request->fee_amount)->toBe('30.00')
        ->and($this->book->fresh()->stock)->toBe(2)
        ->and($librarian->notifications()->first()->data['title'])->toBe('New borrow request');

    \Illuminate\Support\Facades\Storage::disk('receipts')->assertExists($request->borrow_receipt_path);
});

it('prices ₱15 per 7 days, including custom multiples of 7', function () {
    expect(Transaction::feeFor(7))->toBe(15.0)
        ->and(Transaction::feeFor(14))->toBe(30.0)
        ->and(Transaction::feeFor(28))->toBe(60.0)
        ->and(Transaction::feeFor(49))->toBe(105.0);

    \Illuminate\Support\Facades\Storage::fake('receipts', ['serve' => true]);
    requestBorrow(['duration' => 'custom', 'custom_days' => 21])->assertHasNoTableActionErrors();

    expect($this->user->transactions()->sole())
        ->duration_days->toBe(21)
        ->fee_amount->toBe('45.00');
});

it('only accepts custom durations in multiples of 7, up to the maximum', function () {
    \Illuminate\Support\Facades\Storage::fake('receipts', ['serve' => true]);

    requestBorrow(['duration' => 'custom', 'custom_days' => 20])->assertHasTableActionErrors(['custom_days']);
    requestBorrow(['duration' => 'custom', 'custom_days' => 91])->assertHasTableActionErrors(['custom_days']);

    expect(Transaction::count())->toBe(0);
});

it('requires a valid, unused 13-digit GCash reference number', function () {
    \Illuminate\Support\Facades\Storage::fake('receipts', ['serve' => true]);

    requestBorrow(['duration' => '7', 'gcash_reference' => null])->assertHasTableActionErrors(['gcash_reference' => 'required']);
    requestBorrow(['duration' => '7', 'gcash_reference' => '12345'])->assertHasTableActionErrors(['gcash_reference']);
    requestBorrow(['duration' => '7', 'gcash_reference' => '10123456789O1'])->assertHasTableActionErrors(['gcash_reference']);

    // One real payment can't back two requests, however it's spaced
    loan(User::factory()->create(), makeBook('Emma'), attributes: ['gcash_reference' => '1012345678901']);
    requestBorrow(['duration' => '7', 'gcash_reference' => '1012345678901'])->assertHasTableActionErrors(['gcash_reference']);

    expect($this->user->transactions()->count())->toBe(0);
});

it('requires a payment receipt', function () {
    Livewire::test(AvailableBooksWidget::class)
        ->callTableAction('borrow', $this->book, data: ['duration' => '7', 'receipt' => null])
        ->assertHasTableActionErrors(['receipt' => 'required']);

    expect(Transaction::count())->toBe(0);
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

it('returning a book only asks staff to check it in: the copy and the limit stay taken until they do', function () {
    $librarian = User::factory()->create(['role' => 'librarian']);
    $loan = loan($this->user, $this->book);
    loan($this->user, makeBook('Two'));
    loan($this->user, makeBook('Three'));

    expect($this->book->fresh()->stock)->toBe(1);

    Livewire::test(MyBorrowedBooksWidget::class)
        ->callTableAction('return', $loan)
        ->assertNotified('Return requested')
        ->assertSee('Awaiting check-in')
        ->assertTableActionHidden('return', $loan->fresh());

    expect($loan->fresh())
        ->status->toBe(TransactionStatus::PendingReturn)
        ->return_date->toBeNull()
        ->return_requested_at->not->toBeNull()
        ->and($this->book->fresh()->stock)->toBe(1)
        ->and($librarian->notifications()->where('data->title', 'Return to verify')->exists())->toBeTrue();

    // Still counts as borrowed, and toward the limit of 3
    expect($this->user->borrowingBlockedReason($this->book))->toBe('You already have "Dune" borrowed.')
        ->and($this->user->accountBorrowingBlockedReason())->toContain('up to 3 books borrowed or requested');
});

it('lets the user cancel a pending request', function () {
    $request = loan($this->user, $this->book, attributes: ['status' => TransactionStatus::Pending]);

    Livewire::test(MyBorrowedBooksWidget::class)
        ->assertSee('Awaiting approval')
        ->assertTableActionHidden('return', $request)
        ->callTableAction('cancelRequest', $request)
        ->assertDontSee('Awaiting approval');

    // Kept as "cancelled" (an unpaid one owes nothing back)
    expect($request->fresh())
        ->status->toBe(TransactionStatus::Cancelled)
        ->refund_status->toBeNull()
        ->and($this->book->fresh()->stock)->toBe(2);
});

it('offers the 1 Month Promo: 31 days for ₱60.00', function () {
    expect(Transaction::DURATION_PRESETS[31])->toBe('1 Month Promo (31 days)')
        ->and(Transaction::feeFor(31))->toBe(60.0)
        ->and(Transaction::isValidDuration(31, custom: false))->toBeTrue()
        // 31 is only valid as the promo, not as a custom value
        ->and(Transaction::isValidDuration(31, custom: true))->toBeFalse();

    \Illuminate\Support\Facades\Storage::fake('receipts', ['serve' => true]);
    requestBorrow(['duration' => '31'])->assertHasNoTableActionErrors()->assertNotified('Request sent');

    expect($this->user->transactions()->sole())
        ->duration_days->toBe(31)
        ->fee_amount->toBe('60.00')
        ->due_date->toBe('2026-11-24');
});

it('shows no return date in the history while a book is still on loan, never the due date', function () {
    $loan = loan($this->user, $this->book, dueDate: '2026-11-01');

    Livewire::test(\App\Filament\User\Widgets\BorrowingHistoryWidget::class)
        ->assertTableColumnStateSet('return_date', null, $loan)
        ->assertDontSee('Nov 1, 2026');

    $loan->update(['status' => TransactionStatus::Returned]);

    Livewire::test(\App\Filament\User\Widgets\BorrowingHistoryWidget::class)
        ->assertSee('Oct 24, 2026');
});

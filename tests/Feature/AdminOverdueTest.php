<?php

use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Resources\Transactions\TransactionResource;
use App\Models\Book;
use App\Models\Transaction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->travelTo('2026-10-24 10:00:00');
    $this->actingAs(User::factory()->create(['role' => 'librarian']));

    $borrower = User::factory()->create(['role' => 'student']);
    $loan = fn (string $title, string $due, array $extra = []) => Transaction::create([
        'user_id' => $borrower->id,
        'book_id' => Book::create(['title' => $title, 'author' => 'A', 'isbn' => (string) fake()->unique()->isbn13(), 'stock' => 1])->id,
        'borrow_date' => '2026-10-01',
        'due_date' => $due,
        'status' => 'approved',
        'fine_status' => 'None',
        ...$extra,
    ]);

    $this->returned = $loan('Returned Book', '2026-09-01', ['status' => 'returned', 'return_date' => '2026-09-01']);
    $this->onLoan = $loan('On Loan Book', '2026-11-20');
    $this->dueSoon = $loan('Due Soon Book', '2026-10-26');
    $this->overdue = $loan('Overdue Book', '2026-10-19');
});

it('floats overdue loans to the top, then active loans by due date, then returned', function () {
    Livewire::test(ListTransactions::class)
        ->assertCanSeeTableRecords([$this->overdue, $this->dueSoon, $this->onLoan, $this->returned], inOrder: true);
});

it('shows a status badge for each loan', function () {
    Livewire::test(ListTransactions::class)
        ->assertSee('Overdue · 5 days')
        ->assertSee('Due in 2 days')
        ->assertSee('On loan')
        ->assertSee('Returned')
        ->assertSeeHtml('bg-transaction-overdue');
});

it('filters to overdue loans only', function () {
    Livewire::test(ListTransactions::class)
        ->filterTable('overdue')
        ->assertCanSeeTableRecords([$this->overdue])
        ->assertCanNotSeeTableRecords([$this->dueSoon, $this->onLoan, $this->returned]);
});

it('shows the overdue count as a navigation badge', function () {
    expect(TransactionResource::getNavigationBadge())->toBe('1');

    $this->overdue->update(['return_date' => '2026-10-24']);

    expect(TransactionResource::getNavigationBadge())->toBeNull();
});

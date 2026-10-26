<?php

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
    $this->travelTo('2026-10-24 15:30:00');
    $this->actingAs(User::factory()->create(['role' => 'student']));
});

function borrow(string $title, string $dueDate, string $status = 'approved'): Transaction
{
    $book = Book::create([
        'title' => $title,
        'author' => 'Author',
        'isbn' => (string) fake()->unique()->isbn13(),
        'stock' => 1,
    ]);

    return Transaction::create([
        'user_id' => auth()->id(),
        'book_id' => $book->id,
        'borrow_date' => '2026-10-01',
        'due_date' => $dueDate,
        'status' => $status,
        'fine_status' => 'None',
    ]);
}

it('counts whole days until the due date', function () {
    expect(borrow('A', '2026-10-24')->daysUntilDue())->toBe(0)
        ->and(borrow('B', '2026-10-27')->daysUntilDue())->toBe(3)
        ->and(borrow('C', '2026-09-09')->daysUntilDue())->toBe(-45);
});

it('flags overdue books with how many days late', function () {
    borrow('Long Overdue', '2026-09-09');
    borrow('Just Overdue', '2026-10-23');

    Livewire::test(MyBorrowedBooksWidget::class)
        ->assertSee('Overdue · 45 days')
        ->assertSee('Overdue · 1 day')
        ->assertDontSee('Overdue · 1 days')
        ->assertSeeHtml('bg-book-overdue');
});

it('reminds about books that are due soon, but not ones due later', function () {
    borrow('Today Book', '2026-10-24');
    borrow('Tomorrow Book', '2026-10-25');
    borrow('Three Days Book', '2026-10-27');
    borrow('Later Book', '2026-10-28');

    Livewire::test(MyBorrowedBooksWidget::class)
        ->assertSee('Due today')
        ->assertSee('Due tomorrow')
        ->assertSee('Due in 3 days')
        ->assertDontSee('Due in 4 days')
        ->assertSeeHtml('bg-book-due-soon')
        ->assertDontSeeHtml('bg-book-overdue');
});

it('lists the most urgent books first and ignores returned ones', function () {
    borrow('Later Book', '2026-11-20');
    borrow('Overdue Book', '2026-10-01');
    borrow('Returned Book', '2026-09-01', 'returned');

    Livewire::test(MyBorrowedBooksWidget::class)
        ->assertSeeInOrder(['Overdue Book', 'Later Book'])
        ->assertDontSee('Returned Book');
});

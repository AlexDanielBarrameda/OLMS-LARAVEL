<?php

use App\Enums\BookRequestStatus;
use App\Filament\Resources\BookRequests\Pages\CreateBookRequest;
use App\Filament\Resources\BookRequests\Pages\ListBookRequests;
use App\Filament\Resources\Books\BookResource;
use App\Filament\Resources\Books\Pages\EditBook;
use App\Filament\User\Widgets\AvailableBooksWidget;
use App\Models\Book;
use App\Models\BookRequest;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->admin = User::factory()->create(['role' => 'super_admin']);
    $this->librarian = User::factory()->create(['role' => 'librarian']);
    $this->otherLibrarian = User::factory()->create(['role' => 'librarian']);
});

function bookRequest(User $by, string $title = 'Atomic Habits', BookRequestStatus $status = BookRequestStatus::Pending): BookRequest
{
    return BookRequest::create([
        'title' => $title,
        'author' => 'James Clear',
        'reason_for_request' => 'Requested by many students.',
        'status' => $status,
        'requested_by' => $by->id,
    ]);
}

it('lets a librarian request a book and notifies the super admin', function () {
    $this->actingAs($this->librarian);

    Livewire::test(CreateBookRequest::class)
        ->fillForm([
            'title' => 'Atomic Habits',
            'author' => 'James Clear',
            'reason_for_request' => 'Self-help course reading list.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $request = BookRequest::sole();

    expect($request->requested_by)->toBe($this->librarian->id)
        ->and($request->status)->toBe(BookRequestStatus::Pending)
        ->and($this->admin->notifications()->first()->data['title'])->toBe('New book request');
});

it('does not let super admins or students create requests', function () {
    expect($this->admin->can('create', BookRequest::class))->toBeFalse()
        ->and(User::factory()->create(['role' => 'student'])->can('create', BookRequest::class))->toBeFalse()
        ->and($this->librarian->can('create', BookRequest::class))->toBeTrue();
});

it('shows librarians only their own requests, and admins all of them', function () {
    $mine = bookRequest($this->librarian, 'Mine');
    $theirs = bookRequest($this->otherLibrarian, 'Theirs');

    $this->actingAs($this->librarian);
    Livewire::test(ListBookRequests::class)
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$theirs])
        ->assertTableActionHidden('approve', $mine);

    $this->actingAs($this->admin);
    Livewire::test(ListBookRequests::class)
        ->assertCanSeeTableRecords([$mine, $theirs]);
});

it('lets the super admin approve or reject, and tells the librarian', function () {
    $approve = bookRequest($this->librarian, 'Approve Me');
    $reject = bookRequest($this->librarian, 'Reject Me');

    $this->actingAs($this->admin);

    Livewire::test(ListBookRequests::class)
        ->callTableAction('approve', $approve)
        ->callTableAction('reject', $reject)
        ->assertTableActionHidden('approve', $approve->fresh());

    expect($approve->fresh())
        ->status->toBe(BookRequestStatus::Approved)
        ->reviewed_by->toBe($this->admin->id)
        ->reviewed_at->not->toBeNull()
        ->and($reject->fresh()->status)->toBe(BookRequestStatus::Rejected)
        ->and($this->librarian->notifications()->pluck('data')->pluck('title')->all())
        ->toContain('Your request for "Approve Me" was approved', 'Your request for "Reject Me" was declined');
});

it('adds an approved request to the catalog as On Order, ready for the librarian to finish', function () {
    $request = bookRequest($this->librarian, 'Atomic Habits');

    $this->actingAs($this->admin);
    Livewire::test(ListBookRequests::class)->callTableAction('approve', $request);

    $book = $request->fresh()->book;

    expect($book)->not->toBeNull()
        ->title->toBe('Atomic Habits')
        ->author->toBe('James Clear')
        ->stock->toBe(0)
        ->isbn->toBeNull()
        ->status->toBe(Book::STATUS_ON_ORDER);

    $notification = $this->librarian->notifications()->first()->data;

    expect($notification['body'])->toContain('On Order')
        ->and($notification['actions'][0]['url'])->toBe(BookResource::getUrl('edit', ['record' => $book], panel: 'admin'));
});

it('links to the existing book instead of creating a duplicate', function () {
    $existing = Book::create(['title' => 'Atomic Habits', 'author' => 'James Clear', 'isbn' => '9780735211292', 'stock' => 2, 'status' => 'Available']);

    bookRequest($this->librarian, '  atomic habits ')->approve($this->admin);

    expect(Book::count())->toBe(1)
        ->and(BookRequest::sole()->book_id)->toBe($existing->id)
        ->and($existing->fresh()->stock)->toBe(2);
});

it('does not touch the catalog when a request is rejected', function () {
    $request = bookRequest($this->librarian);

    $this->actingAs($this->admin);
    Livewire::test(ListBookRequests::class)->callTableAction('reject', $request);

    expect(Book::count())->toBe(0)
        ->and($request->fresh()->book_id)->toBeNull();
});

it('keeps On Order books away from students', function () {
    $onOrder = bookRequest($this->librarian, 'Not Here Yet')->approve($this->admin);
    $onShelf = Book::create(['title' => 'On The Shelf', 'author' => 'A', 'isbn' => '9780000000002', 'stock' => 1, 'status' => 'Available']);

    Filament::setCurrentPanel(Filament::getPanel('user'));
    $this->actingAs(User::factory()->create(['role' => 'student']));

    Livewire::test(AvailableBooksWidget::class)
        ->assertCanSeeTableRecords([$onShelf])
        ->assertCanNotSeeTableRecords([$onOrder]);
});

it('requires an ISBN once a book is no longer on order', function () {
    $book = bookRequest($this->librarian)->approve($this->admin);

    $this->actingAs($this->librarian);

    // An existing category outside the old hard-coded list
    Book::create(['title' => 'Other', 'author' => 'B', 'isbn' => '9780000000019', 'stock' => 1, 'status' => 'Available', 'category' => 'Self-Help']);

    // Still on order: other details can be saved without an ISBN
    Livewire::test(EditBook::class, ['record' => $book->getRouteKey()])
        ->fillForm(['category' => 'Self-Help'])
        ->call('save')
        ->assertHasNoFormErrors();

    // Marking it available needs the ISBN
    Livewire::test(EditBook::class, ['record' => $book->getRouteKey()])
        ->fillForm(['status' => 'Available', 'stock' => 3])
        ->call('save')
        ->assertHasFormErrors(['isbn' => 'required']);

    Livewire::test(EditBook::class, ['record' => $book->getRouteKey()])
        ->fillForm(['status' => 'Available', 'stock' => 3, 'isbn' => '9780735211292'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($book->fresh())->status->toBe('Available')->stock->toBe(3)->isbn->toBe('9780735211292');
});

it('lets librarians edit or withdraw only their own pending requests', function () {
    $pending = bookRequest($this->librarian);
    $reviewed = bookRequest($this->librarian, status: BookRequestStatus::Approved);

    expect($this->librarian->can('update', $pending))->toBeTrue()
        ->and($this->librarian->can('update', $reviewed))->toBeFalse()
        ->and($this->otherLibrarian->can('update', $pending))->toBeFalse()
        ->and($this->librarian->can('delete', $pending))->toBeTrue()
        ->and($this->librarian->can('delete', $reviewed))->toBeFalse();
});

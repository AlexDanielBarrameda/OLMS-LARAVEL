<?php

use App\Enums\BookRequestStatus;
use App\Filament\Resources\BookRequests\Pages\CreateBookRequest;
use App\Filament\Resources\BookRequests\Pages\ListBookRequests;
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

it('lets librarians edit or withdraw only their own pending requests', function () {
    $pending = bookRequest($this->librarian);
    $reviewed = bookRequest($this->librarian, status: BookRequestStatus::Approved);

    expect($this->librarian->can('update', $pending))->toBeTrue()
        ->and($this->librarian->can('update', $reviewed))->toBeFalse()
        ->and($this->otherLibrarian->can('update', $pending))->toBeFalse()
        ->and($this->librarian->can('delete', $pending))->toBeTrue()
        ->and($this->librarian->can('delete', $reviewed))->toBeFalse();
});

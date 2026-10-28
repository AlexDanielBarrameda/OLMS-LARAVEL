<?php

use App\Enums\TransactionStatus;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\Transactions\Pages\ListTransactions;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\RelationManagers\ActivityRelationManager;
use App\Models\Book;
use App\Models\Transaction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->admin = User::factory()->create(['role' => 'super_admin']);
    $this->librarian = User::factory()->create(['role' => 'librarian']);
    $this->student = User::factory()->create(['role' => 'student']);
});

it('logs who created, changed and deleted a book, with only the changed fields', function () {
    $this->actingAs($this->librarian);

    $book = Book::create(['title' => 'Dune', 'author' => 'Frank Herbert', 'isbn' => '9780441013593', 'stock' => 3, 'status' => 'Available']);
    $book->update(['stock' => 5]);
    $book->update(['stock' => 5]); // no change: nothing logged
    $book->delete();

    $log = Activity::where('subject_type', Book::class)->oldest('id')->get();

    expect($log->pluck('event')->all())->toBe(['created', 'updated', 'deleted'])
        ->and($log->every(fn (Activity $a) => $a->causer_id === $this->librarian->id))->toBeTrue()
        ->and($log[1]->properties['attributes'])->toBe(['stock' => 5])
        ->and($log[1]->properties['old'])->toBe(['stock' => 3]);
});

it('records the librarian who approved a borrow request', function () {
    $book = Book::create(['title' => 'Dune', 'author' => 'Frank Herbert', 'isbn' => '9780441013593', 'stock' => 2]);
    $request = Transaction::create([
        'user_id' => $this->student->id, 'book_id' => $book->id, 'status' => TransactionStatus::Pending,
        'borrow_date' => now()->toDateString(), 'due_date' => now()->addDays(14)->toDateString(), 'fine_status' => 'None',
    ]);

    $this->actingAs($this->librarian);
    Livewire::test(ListTransactions::class)->callTableAction('approve', $request);

    $approval = Activity::where('subject_type', Transaction::class)->where('event', 'updated')->sole();

    expect($approval->causer_id)->toBe($this->librarian->id)
        ->and($approval->properties['old']['status'])->toBe('pending')
        ->and($approval->properties['attributes']['status'])->toBe('approved');
});

it('is only available to super admins', function () {
    $this->actingAs($this->librarian);
    expect(ActivityLogResource::canViewAny())->toBeFalse();
    $this->get(ActivityLogResource::getUrl('index'))->assertForbidden();

    $this->actingAs($this->admin);
    expect(ActivityLogResource::canViewAny())->toBeTrue()
        ->and(ActivityLogResource::getNavigationGroup())->toBe('User Management');
    $this->get(ActivityLogResource::getUrl('index'))->assertOk()->assertSee('Activity Log');
});

it('lists activity newest first with readable details and no way to change it', function () {
    $this->actingAs($this->librarian);
    $book = Book::create(['title' => 'Dune', 'author' => 'Frank Herbert', 'isbn' => '9780441013593', 'stock' => 3]);
    $this->travel(1)->minute();
    $book->update(['stock' => 1]);

    [$created, $updated] = Activity::oldest('id')->get();

    $this->actingAs($this->admin);
    Livewire::test(ListActivityLogs::class)
        ->assertCanSeeTableRecords([$updated, $created], inOrder: true)
        ->assertSee(['Book', 'Dune', $this->librarian->name, 'Librarian', 'Stock'])
        ->assertTableActionExists('details')
        ->assertTableActionDoesNotExist('edit')
        ->assertTableActionDoesNotExist('delete');

    // The Details modal's before/after table
    $details = view('filament.activity-log.details', [
        'activity' => $updated,
        'changes' => \App\Filament\Resources\ActivityLogs\ActivityPresenter::changes($updated),
    ])->render();

    expect($details)->toContain('Before', 'After', '<td class="bg-activity-old">3</td>', '<td>1</td>', $this->librarian->name);
});

it("shows a user's own activity on their page", function () {
    $this->actingAs($this->librarian);
    Book::create(['title' => 'Mine', 'author' => 'A', 'isbn' => '9780000000002', 'stock' => 1]);

    $this->actingAs($this->admin);
    Book::create(['title' => 'Admin Book', 'author' => 'B', 'isbn' => '9780000000019', 'stock' => 1]);

    Livewire::test(ActivityRelationManager::class, ['ownerRecord' => $this->librarian, 'pageClass' => EditUser::class])
        ->assertCanSeeTableRecords(Activity::where('causer_id', $this->librarian->id)->get())
        ->assertCanNotSeeTableRecords(Activity::where('causer_id', $this->admin->id)->get())
        ->assertSee('Mine')
        ->assertDontSee('Admin Book');
});

<?php

use App\Enums\TransactionStatus;
use App\Filament\Resources\Books\Pages\ListBooks;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Livewire\Auth\Login;
use App\Models\Book;
use App\Models\Transaction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->admin = User::factory()->create(['role' => 'super_admin']);
    $this->student = User::factory()->create(['role' => 'student']);
    $this->borrowed = Book::create(['title' => 'Dune', 'author' => 'Frank Herbert', 'isbn' => '9780441013593', 'stock' => 2]);
    $this->unused = Book::create(['title' => 'Emma', 'author' => 'Jane Austen', 'isbn' => '9780141439587', 'stock' => 1]);

    Transaction::create([
        'user_id' => $this->student->id, 'book_id' => $this->borrowed->id, 'status' => TransactionStatus::Returned,
        'borrow_date' => '2026-10-01', 'due_date' => '2026-10-15', 'return_date' => '2026-10-10', 'fine_status' => 'None',
    ]);
});

it('keeps books and users with borrowing history from being deleted', function () {
    $this->actingAs($this->admin);
    $newcomer = User::factory()->create(['role' => 'student']);

    Livewire::test(ListBooks::class)
        ->assertTableActionHidden('delete', $this->borrowed)
        ->assertTableActionVisible('delete', $this->unused);

    Livewire::test(ListUsers::class)
        ->assertTableActionHidden('delete', $this->student)
        ->assertTableActionVisible('delete', $newcomer)
        // Not even your own account
        ->assertTableActionHidden('delete', $this->admin);
});

it('skips books with history in a bulk delete, and deletes the rest', function () {
    $this->actingAs($this->admin);

    Livewire::test(ListBooks::class)
        ->callTableBulkAction('delete', [$this->borrowed, $this->unused]);

    expect(Book::find($this->borrowed->id))->not->toBeNull()
        ->and(Book::find($this->unused->id))->toBeNull()
        ->and(Transaction::count())->toBe(1);
});

it('refuses in the database too, so the transaction history survives any delete path', function () {
    expect(fn () => $this->borrowed->delete())->toThrow(QueryException::class)
        ->and(fn () => User::query()->whereKey($this->student->id)->delete())->toThrow(QueryException::class)
        ->and(Transaction::count())->toBe(1);
});

it("won't sign in a deactivated account, and only says so to someone with the right password", function () {
    $inactive = User::factory()->create(['email' => 'gone@example.com', 'password' => 'secret-pass', 'status' => 'Inactive']);

    Livewire::test(Login::class)
        ->set('email', 'gone@example.com')
        ->set('password', 'secret-pass')
        ->call('authenticate')
        ->assertHasErrors(['email'])
        ->assertSee('This account has been deactivated.');

    $this->assertGuest();

    Livewire::test(Login::class)
        ->set('email', 'gone@example.com')
        ->set('password', 'wrong-pass')
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertDontSee('deactivated');

    expect($inactive->canAccessPanel(Filament::getPanel('user')))->toBeFalse();
});

it('locks a deactivated account out of the panels, even with a session still open', function () {
    $this->admin->update(['status' => 'inactive']);
    $this->student->update(['status' => 'Inactive']);

    $this->actingAs($this->admin)->get('/admin')->assertForbidden();
    $this->actingAs($this->student)->get('/user')->assertForbidden();
});

it('only accepts images up to 5 MB as temporary uploads', function () {
    expect(config('livewire.temporary_file_upload.rules'))->toBe(['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:5120'])
        // The rest of Livewire's defaults still apply
        ->and(config('livewire.class_namespace'))->toBe('App\\Livewire');
});

it('shows book covers as decorative images, since the title is printed below', function () {
    Filament::setCurrentPanel(Filament::getPanel('user'));
    $this->actingAs($this->student);

    Livewire::test(\App\Filament\User\Widgets\AvailableBooksWidget::class)
        ->assertSeeHtml('alt=""')
        ->assertDontSeeHtml('alt="Dune"');
});

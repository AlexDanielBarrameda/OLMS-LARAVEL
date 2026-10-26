<?php

use App\Enums\TransactionStatus;
use App\Filament\Pages\AdminDashboard;
use App\Filament\Pages\Profile;
use App\Filament\Resources\Books\Pages\ListBooks;
use App\Filament\Widgets\DashboardStats;
use App\Filament\Widgets\RecentActivity;
use App\Models\Book;
use App\Models\Transaction;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->travelTo('2026-10-26 09:00:00');
    $this->librarian = User::factory()->create(['role' => 'librarian', 'status' => 'active']);
    $this->admin = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);
    $this->student = User::factory()->create(['role' => 'student', 'status' => 'active']);
});

function adminLoan(User $user, Book $book, TransactionStatus $status, string $due = '2026-11-05', array $extra = []): Transaction
{
    return Transaction::create([
        'user_id' => $user->id,
        'book_id' => $book->id,
        'status' => $status,
        'borrow_date' => '2026-10-01',
        'due_date' => $due,
        'fine_status' => 'None',
        ...$extra,
    ]);
}

function adminBook(string $title, int $stock = 5): Book
{
    return Book::create(['title' => $title, 'author' => 'A', 'isbn' => (string) fake()->unique()->isbn13(), 'stock' => $stock]);
}

it('names the dashboard after the role', function () {
    $this->actingAs($this->librarian);
    $this->get(AdminDashboard::getUrl())->assertOk()->assertSee('Librarian Dashboard')->assertDontSee('Admin Dashboard');

    $this->actingAs($this->admin);
    $this->get(AdminDashboard::getUrl())->assertOk()->assertSee('Admin Dashboard');
});

it('has no dropdown or sign out in the top bar; the avatar links to the profile, which has sign out', function () {
    $this->actingAs($this->librarian);

    $this->get(AdminDashboard::getUrl())
        ->assertOk()
        ->assertDontSee('fi-user-menu', escape: false)
        ->assertDontSee('fi-account-widget', escape: false)
        ->assertDontSee(filament()->getLogoutUrl(), escape: false)
        ->assertSee('bg-appearance bg-appearance-compact', escape: false)
        ->assertSeeInOrder(['href="'.Profile::getUrl().'"', 'class="bg-avatar-link"'], escape: false);

    $this->get(Profile::getUrl())
        ->assertOk()
        ->assertSee('Librarian')
        ->assertSee('Change password')
        ->assertSee(filament()->getLogoutUrl(), escape: false);
});

it('counts real data in the stats', function () {
    $this->actingAs($this->librarian);
    $book = adminBook('Dune');

    adminLoan($this->student, $book, TransactionStatus::Approved);                    // on loan
    adminLoan($this->student, adminBook('Emma'), TransactionStatus::Approved, '2026-10-01'); // overdue
    adminLoan($this->student, adminBook('Ivanhoe'), TransactionStatus::Pending);        // not out yet
    adminLoan($this->student, adminBook('Ulysses'), TransactionStatus::Returned);       // back

    Livewire::test(DashboardStats::class)
        ->assertSeeInOrder(['Borrowed Books', '2', 'Active Users', '1', 'Pending Fines', '1']);
});

it('shows what actually happened in recent activity', function () {
    $this->actingAs($this->librarian);

    adminLoan($this->student, adminBook('Requested Book'), TransactionStatus::Pending);
    adminLoan($this->student, adminBook('Returned Book'), TransactionStatus::Returned);
    adminLoan($this->student, adminBook('Late Book'), TransactionStatus::Approved, '2026-10-01');

    Livewire::test(RecentActivity::class)
        ->assertSee(['Requested', 'Returned', 'Overdue'])
        ->assertDontSee('>None<', escape: false);
});

it('alerts staff when a book drops to its last copy or runs out', function () {
    $book = adminBook('Dune', stock: 2);

    $book->decrement('stock');
    $book->decrement('stock');
    $book->increment('stock');

    $titles = $this->librarian->notifications()->pluck('data')->pluck('title')->all();

    // 2 → 1 (low), 1 → 0 (out); going back up to 1 doesn't alert
    expect($titles)->toEqualCanonicalizing(['"Dune" is low on stock', '"Dune" is out of stock'])
        ->and($this->admin->notifications()->count())->toBe(2)
        ->and($this->student->notifications()->count())->toBe(0);
});

it('marks popular books and filters low stock', function () {
    $this->actingAs($this->librarian);
    $popular = adminBook('Popular Book', stock: 10);
    $quiet = adminBook('Quiet Book', stock: 1);

    foreach (range(1, Book::POPULAR_AFTER) as $i) {
        adminLoan(User::factory()->create(['role' => 'student']), $popular, TransactionStatus::Returned);
    }
    adminLoan($this->student, $quiet, TransactionStatus::Pending);

    Livewire::test(ListBooks::class)
        ->assertSee('Popular · '.Book::POPULAR_AFTER)
        ->filterTable('low_stock')
        ->assertCanSeeTableRecords([$quiet])
        ->assertCanNotSeeTableRecords([$popular]);
});

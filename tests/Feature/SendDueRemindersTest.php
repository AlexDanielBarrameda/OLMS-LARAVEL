<?php

use App\Models\Book;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo('2026-10-24 08:00:00');
    $this->user = User::factory()->create(['role' => 'student']);

    $loan = fn (string $title, string $due, array $extra = []) => Transaction::create([
        'user_id' => $this->user->id,
        'book_id' => Book::create(['title' => $title, 'author' => 'A', 'isbn' => (string) fake()->unique()->isbn13(), 'stock' => 1])->id,
        'borrow_date' => '2026-10-01',
        'due_date' => $due,
        'fine_status' => 'None',
        ...$extra,
    ]);

    $loan('Overdue Book', '2026-10-21');
    $loan('Tomorrow Book', '2026-10-25');
    $loan('Later Book', '2026-11-10');
    $loan('Returned Book', '2026-10-01', ['fine_status' => 'Returned', 'return_date' => '2026-10-02']);
});

it('notifies borrowers about overdue and soon-due books only', function () {
    $this->artisan('app:send-due-reminders')
        ->expectsOutputToContain('Sent 2 reminders (1 overdue, 1 due soon)')
        ->assertSuccessful();

    $titles = $this->user->notifications()->get()->pluck('data.title');

    expect($titles)->toHaveCount(2)
        ->toContain('"Overdue Book" is 3 days overdue')
        ->toContain('"Tomorrow Book" is due tomorrow');

    expect($this->user->notifications()->where('data->status', 'danger')->count())->toBe(1);
});

it('does not send the same reminder twice on one day', function () {
    $this->artisan('app:send-due-reminders')->assertSuccessful();

    $this->artisan('app:send-due-reminders')
        ->expectsOutputToContain('Sent 0 reminders (0 overdue, 0 due soon). Skipped 2 already sent today.')
        ->assertSuccessful();

    expect($this->user->notifications()->count())->toBe(2);

    $this->travel(1)->day();
    $this->artisan('app:send-due-reminders')->assertSuccessful();

    expect($this->user->notifications()->count())->toBe(4);
});

it('catches a user up on reminders when they log in, once per day', function () {
    $other = User::factory()->create(['role' => 'student']);

    \Illuminate\Support\Facades\Auth::login($this->user);

    expect($this->user->notifications()->count())->toBe(2)
        ->and($other->notifications()->count())->toBe(0);

    \Illuminate\Support\Facades\Auth::logout();
    \Illuminate\Support\Facades\Auth::login($this->user);

    expect($this->user->notifications()->count())->toBe(2);

    // The daily run afterwards doesn't duplicate what login already sent
    $this->artisan('app:send-due-reminders')
        ->expectsOutputToContain('Sent 0 reminders (0 overdue, 0 due soon). Skipped 2 already sent today.');
});

it('is scheduled to run daily', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event) => str_contains($event->command ?? '', 'app:send-due-reminders'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('0 8 * * *');
});

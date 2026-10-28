<?php

namespace App\Filament\User\Widgets;

use App\Enums\TransactionStatus;
use App\Models\Book;
use App\Models\Transaction;
use App\Models\User;
use App\Services\LibraryNotifications;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class AvailableBooksWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Available Books';

    // book_id => status of this user's open requests/loans, loaded once per render instead of per card
    protected ?array $openStatuses = null;

    // Refresh when another widget changes a loan (a book returned, a request cancelled)
    #[On('update-book-tables')]
    public function refreshAvailableBooks(): void
    {
        $this->openStatuses = null;
        $this->accountBlockLoaded = false;
    }

    // Overdue loans or the borrowing limit block every book; checked once per render
    protected ?string $accountBlock = null;

    protected bool $accountBlockLoaded = false;

    protected function accountBlockReason(): ?string
    {
        if (! $this->accountBlockLoaded) {
            $this->accountBlock = Auth::user()?->accountBorrowingBlockedReason();
            $this->accountBlockLoaded = true;
        }

        return $this->accountBlock;
    }

    // "7 days · ₱15.00", "14 days · ₱30.00", "1 month (28 days) · ₱60.00", "Custom"
    protected static function durationOptions(): array
    {
        return collect(Transaction::DURATION_PRESETS)
            ->mapWithKeys(fn (string $label, int $days) => [(string) $days => $label.' · '.Transaction::peso(Transaction::feeFor($days))])
            ->put('custom', 'Custom (multiples of 7 days)')
            ->all();
    }

    // Days chosen in the form, or null if the custom value isn't a valid multiple of 7 in range
    protected static function requestedDays(mixed $duration, mixed $customDays): ?int
    {
        $custom = $duration === 'custom';
        $days = $custom ? (int) $customDays : (int) $duration;

        // Presets (including the 31-day promo) are valid as listed; custom must be a multiple of 7
        return Transaction::isValidDuration($days, $custom) ? $days : null;
    }

    protected static function feeViewData(?int $days): array
    {
        $amount = $days ? Transaction::peso(Transaction::feeFor($days)) : '—';
        $due = $days ? ' · due '.today()->addDays($days)->format('M j').' if approved today' : '';

        return [
            'label' => 'Borrowing fee',
            'amount' => $amount,
            'breakdown' => match (true) {
                $days === null => 'Enter a multiple of 7 days, up to '.Transaction::MAX_DURATION_DAYS.'.',
                $days === 31 => '31 days for the price of 28 · 3 days free'.$due,
                default => $days.' days ('.($days / 7).' × '.Transaction::peso(Transaction::FEE_PER_WEEK).' per 7 days)'.$due,
            },
            'steps' => [
                'Scan the QR with GCash (or save it and upload it in GCash) and pay <strong>'.e($amount).'</strong>.',
                'Enter the 13-digit reference number from your GCash receipt and upload a screenshot of it below.',
                'A librarian finds your payment in the library\'s GCash and approves the request. If it can\'t be approved, your fee is refunded.',
            ],
            'qrUrl' => asset('images/gcash-qr.png'),
        ];
    }

    protected function openStatusFor(Book $book): ?TransactionStatus
    {
        $this->openStatuses ??= Transaction::query()
            ->where('user_id', Auth::id())
            ->open()
            ->get(['book_id', 'status'])
            ->mapWithKeys(fn (Transaction $loan) => [$loan->book_id => $loan->status])
            ->all();

        return $this->openStatuses[$book->id] ?? null;
    }

    public function table(Table $table): Table
    {
        return $table
            // Books still on order aren't in the library yet
            ->query(Book::query()->inCatalog())
            ->extraAttributes(['class' => 'bg-books'])
            ->contentGrid([
                'default' => 2,
                'sm' => 3,
                'lg' => 4,
                'xl' => 5,
            ])
            ->columns([
                Stack::make([
                    Tables\Columns\ImageColumn::make('cover')
                        ->state(fn (Book $record) => $record->coverUrl())
                        ->imageWidth('100%')
                        ->imageHeight('auto')
                        ->extraImgAttributes(fn (Book $record) => [
                            // Decorative: the title is printed right below, so screen readers shouldn't hear it twice
                            'alt' => '',
                            'loading' => 'lazy',
                            'class' => 'bg-book-cover',
                            'onerror' => "this.onerror=null;this.src='{$record->fallbackCoverUrl()}'",
                        ]),
                    Tables\Columns\TextColumn::make('title')->weight('bold')->size('lg')->searchable(),
                    Tables\Columns\TextColumn::make('author')->color('gray')->searchable(),
                    Tables\Columns\TextColumn::make('category')
                        ->formatStateUsing(fn ($state) => "Category: {$state}"),
                    Tables\Columns\TextColumn::make('stock')
                        ->formatStateUsing(fn ($state) => "{$state} left")
                        ->badge()
                        ->color(fn ($state) => $state > 0 ? 'gray' : 'danger'),
                ])->space(2),
            ])
            ->actions([
                Action::make('borrow')
                    ->label(fn (Book $record) => match ($this->openStatusFor($record)) {
                        TransactionStatus::Pending => 'Requested',
                        TransactionStatus::Approved, TransactionStatus::PendingFineVerification => 'Borrowed',
                        TransactionStatus::PendingReturn => 'Returning',
                        default => 'Request to Borrow',
                    })
                    ->button()
                    ->color('primary')
                    // Blocked students find out before paying, not after
                    ->disabled(fn (Book $record) => $record->stock <= 0 || $this->openStatusFor($record) !== null || $this->accountBlockReason() !== null)
                    ->tooltip(fn (Book $record) => $this->openStatusFor($record) === null ? $this->accountBlockReason() : null)
                    ->modalIcon('heroicon-o-book-open')
                    ->modalHeading(fn (Book $record) => "Borrow \"{$record->title}\"")
                    ->modalDescription('Choose how long you need it and pay the fee via GCash. A librarian will approve your request, and your borrowing period starts then.')
                    ->modalWidth('lg')
                    ->schema([
                        Select::make('duration')
                            ->label('Borrow for')
                            ->options(static::durationOptions())
                            ->default('7')
                            ->required()
                            ->selectablePlaceholder(false)
                            ->live(),
                        TextInput::make('custom_days')
                            ->label('Number of days')
                            ->helperText('Multiples of 7, up to '.Transaction::MAX_DURATION_DAYS.' days. ₱15 per 7 days.')
                            ->numeric()
                            ->integer()
                            ->step(7)
                            ->minValue(7)
                            ->maxValue(Transaction::MAX_DURATION_DAYS)
                            ->multipleOf(7)
                            ->default(21)
                            ->required(fn (Get $get) => $get('duration') === 'custom')
                            ->visible(fn (Get $get) => $get('duration') === 'custom')
                            ->live(debounce: 400),
                        View::make('filament.user.payment')
                            ->viewData(fn (Get $get) => static::feeViewData(static::requestedDays($get('duration'), $get('custom_days')))),
                        // Staff search the library's GCash for this before approving (or refunding), so a doctored screenshot gets nowhere
                        TextInput::make('gcash_reference')
                            ->label('GCash reference number')
                            ->placeholder('e.g. 1012345678901')
                            ->helperText('The 13-digit "Ref No." on your GCash receipt.')
                            ->required()
                            // Digits only as you type or paste, so "1012 345 678901" becomes "1012345678901"
                            ->mask(str_repeat('9', Transaction::GCASH_REFERENCE_LENGTH))
                            ->length(Transaction::GCASH_REFERENCE_LENGTH)
                            ->inputMode('numeric')
                            // One real payment can't back two requests
                            ->rules(['digits:'.Transaction::GCASH_REFERENCE_LENGTH, Rule::unique('transactions', 'gcash_reference')])
                            ->validationMessages([
                                'digits' => 'Enter the 13-digit reference number from your GCash receipt.',
                                'size' => 'Enter the 13-digit reference number from your GCash receipt.',
                                'unique' => 'This reference number has already been used for another request.',
                            ]),
                        FileUpload::make('receipt')
                            ->label('Payment receipt / screenshot')
                            ->helperText('A screenshot of the GCash confirmation, showing the amount and reference number. PNG or JPG, up to 5 MB.')
                            ->required()
                            ->image()
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->maxSize(5120)
                            ->disk('receipts')
                            ->directory('borrow/'.now()->format('Y/m'))
                            ->visibility('private'),
                    ])
                    ->modalSubmitActionLabel('Send request')
                    ->action(function (Book $record, array $data, Component $livewire, Action $action) {
                        // Never trust the client's amount: recompute it from the chosen duration
                        $days = static::requestedDays($data['duration'] ?? null, $data['custom_days'] ?? null);

                        if ($days === null) {
                            Notification::make()->title('Choose a valid duration')->body('Custom durations must be a multiple of 7 days.')->danger()->send();
                            $action->halt();
                        }

                        $reference = $data['gcash_reference'];

                        // Lock the user and the book so double clicks can't slip past the rules
                        try {
                            $result = DB::transaction(function () use ($record, $data, $days, $reference): Transaction|string {
                                $user = User::lockForUpdate()->findOrFail(Auth::id());
                                $book = Book::lockForUpdate()->findOrFail($record->id);

                                if ($book->stock <= 0) {
                                    return "\"{$book->title}\" is out of stock.";
                                }

                                if ($reason = $user->borrowingBlockedReason($book)) {
                                    return $reason;
                                }

                                // Stock is only taken when a librarian approves; the paid-for period starts then too
                                return Transaction::create([
                                    'user_id' => $user->id,
                                    'book_id' => $book->id,
                                    'status' => TransactionStatus::Pending,
                                    'borrow_date' => today()->toDateString(),
                                    'due_date' => today()->addDays($days)->toDateString(),
                                    'duration_days' => $days,
                                    'fee_amount' => Transaction::feeFor($days),
                                    'gcash_reference' => $reference,
                                    'borrow_receipt_path' => $data['receipt'],
                                    'fine_status' => 'None',
                                ]);
                            });

                            // They say they've paid: keep a rejected record with the reference and receipt. Staff check the payment arrived before it's refunded.
                            $refused = is_string($result) ? Transaction::create([
                                'user_id' => Auth::id(),
                                'book_id' => $record->id,
                                'status' => TransactionStatus::Rejected,
                                'borrow_date' => today()->toDateString(),
                                'due_date' => today()->addDays($days)->toDateString(),
                                'duration_days' => $days,
                                'fee_amount' => Transaction::feeFor($days),
                                'gcash_reference' => $reference,
                                'borrow_receipt_path' => $data['receipt'],
                                'fine_status' => 'None',
                            ]) : null;
                        } catch (UniqueConstraintViolationException) {
                            // The same reference was submitted twice at once; the form rule catches it otherwise
                            Notification::make()->title('This reference number has already been used')->danger()->send();
                            $action->halt();
                        }

                        if ($refused) {
                            app(LibraryNotifications::class)->refundNeeded($refused->load('user', 'book'), $result);

                            Notification::make()
                                ->title("Can't request this book")
                                ->body(LibraryNotifications::sentences($result, LibraryNotifications::refundPromise($refused)))
                                ->danger()
                                ->send();

                            $action->cancel();
                        }

                        app(LibraryNotifications::class)->borrowRequested($result->load('user', 'book'));

                        Notification::make()
                            ->title('Request sent')
                            ->body('We\'ll let you know when a librarian approves it. Your '.$days.' days start then.')
                            ->success()
                            ->send();

                        $this->openStatuses = null;
                        $this->accountBlockLoaded = false;

                        $livewire->dispatch('update-book-tables');
                    }),
            ]);
    }
}
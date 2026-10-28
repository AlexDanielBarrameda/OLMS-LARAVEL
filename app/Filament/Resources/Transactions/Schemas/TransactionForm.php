<?php

namespace App\Filament\Resources\Transactions\Schemas;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;

class TransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required(),
                Select::make('book_id')
                    ->relationship('book', 'title')
                    ->searchable()
                    ->required(),
                // Read-only: status only changes through the table actions (Approve, Reject, Verify Return...), which check payments and returns.
                // A loan created here is a desk loan, saved as "On loan" by CreateTransaction.
                Select::make('status')
                    ->options(TransactionStatus::class)
                    ->default(TransactionStatus::Approved)
                    ->disabled()
                    ->dehydrated(false)
                    ->native(false),
                DatePicker::make('borrow_date')
                    ->default(now())
                    ->required(),
                DatePicker::make('due_date')
                    ->default(now()->addDays(Transaction::LOAN_DAYS))
                    ->required(),
                // Read-only too: a return date marks the loan returned (see Transaction::saving), so only "Verify Return" sets it
                DatePicker::make('return_date')
                    ->disabled()
                    ->dehydrated(false),
                Select::make('fine_status')
                    ->options([
                        'None' => 'None',
                        'Pending' => 'Pending',
                        'Paid' => 'Paid',
                    ])
                    ->default('None')
                    ->required(),
            ]);
    }
}
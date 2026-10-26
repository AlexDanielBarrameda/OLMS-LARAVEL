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
                // Approving here (a desk loan) takes a copy off the shelf; Returned puts it back
                Select::make('status')
                    ->options(TransactionStatus::class)
                    ->default(TransactionStatus::Approved)
                    ->required()
                    ->native(false),
                DatePicker::make('borrow_date')
                    ->default(now())
                    ->required(),
                DatePicker::make('due_date')
                    ->default(now()->addDays(Transaction::LOAN_DAYS))
                    ->required(),
                DatePicker::make('return_date'),
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
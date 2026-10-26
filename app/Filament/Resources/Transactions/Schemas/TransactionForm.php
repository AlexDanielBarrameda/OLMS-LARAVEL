<?php

namespace App\Filament\Resources\Transactions\Schemas;

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
                DatePicker::make('borrow_date')
                    ->default(now())
                    ->required(),
                DatePicker::make('due_date')
                    ->required(),
                DatePicker::make('return_date'),
                Select::make('fine_status')
                    ->options([
                        'None' => 'None',
                        'Pending' => 'Pending',
                        'Paid' => 'Paid',
                        // Set when a user returns a book from their dashboard
                        'Returned' => 'Returned',
                    ])
                    ->default('None')
                    ->required(),
            ]);
    }
}
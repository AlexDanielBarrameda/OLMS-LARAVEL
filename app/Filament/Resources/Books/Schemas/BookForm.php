<?php

namespace App\Filament\Resources\Books\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;

class BookForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                TextInput::make('author')
                    ->required()
                    ->maxLength(255),
                TextInput::make('isbn')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Select::make('category')
                    ->options([
                        'Fiction' => 'Fiction',
                        'Science' => 'Science',
                        'History' => 'History',
                        'Biography' => 'Biography',
                        'Romance' => 'Romance',
                        'Other' => 'Other',
                    ]),
                TextInput::make('stock')
                    ->numeric()
                    ->default(1)
                    ->required(),
                Select::make('status')
                    ->options([
                        'Available' => 'Available',
                        'Borrowed' => 'Borrowed',
                        'Damaged' => 'Damaged',
                    ])
                    ->default('Available')
                    ->required(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\Books\Schemas;

use App\Models\Book;
use Filament\Schemas\Components\Utilities\Get;
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
                // Books on order may not have an ISBN yet; it's needed once the book is in the library
                TextInput::make('isbn')
                    ->label('ISBN')
                    ->required(fn (Get $get): bool => $get('status') !== Book::STATUS_ON_ORDER)
                    ->helperText(fn (Get $get): ?string => $get('status') === Book::STATUS_ON_ORDER
                        ? 'Add it when the book arrives. The cover is looked up from the ISBN.'
                        : null)
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                // The defaults plus every category already in the catalog, so existing books can be saved
                Select::make('category')
                    ->options(fn (): array => collect(['Fiction', 'Science', 'History', 'Biography', 'Romance', 'Other'])
                        ->merge(Book::query()->whereNotNull('category')->distinct()->pluck('category'))
                        ->unique()
                        ->sort()
                        ->mapWithKeys(fn (string $category) => [$category => $category])
                        ->all())
                    ->searchable(),
                TextInput::make('stock')
                    ->numeric()
                    ->default(1)
                    ->required(),
                Select::make('status')
                    ->options([
                        'Available' => 'Available',
                        'Borrowed' => 'Borrowed',
                        'Damaged' => 'Damaged',
                        Book::STATUS_ON_ORDER => Book::STATUS_ON_ORDER,
                    ])
                    ->live()
                    ->default('Available')
                    ->required(),
            ]);
    }
}

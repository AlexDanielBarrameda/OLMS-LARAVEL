<?php

namespace App\Filament\Resources\BookRequests\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BookRequestForm
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
                Textarea::make('reason_for_request')
                    ->label('Why should the library get it?')
                    ->helperText('Who will use it, and for what (a course, a reading list, frequent requests).')
                    ->required()
                    ->rows(4)
                    ->maxLength(2000)
                    ->columnSpanFull(),
            ]);
    }
}

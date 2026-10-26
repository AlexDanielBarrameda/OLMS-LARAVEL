<?php

namespace App\Filament\User\Widgets;

use App\Models\Transaction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\Auth;

class BorrowingHistoryWidget extends BaseWidget
{
    protected static bool $isLazy = false;

    protected int | string | array $columnSpan = 'full';
    protected static ?string $heading = 'Borrowing History';
    protected static ?int $sort = 3; // Puts it at the bottom

    public function table(Table $table): Table
    {
        return $table
            ->query(
                // Get all transactions for this user, newest first
                Transaction::query()->where('user_id', Auth::id())->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('book.title')->label('Book'),
                Tables\Columns\TextColumn::make('book.author')->label('Author'),
                Tables\Columns\TextColumn::make('status')->badge(),
                Tables\Columns\TextColumn::make('borrow_date')
                    ->date()
                    ->label('Borrow Date'),
                Tables\Columns\TextColumn::make('due_date')
                    ->date()
                    ->label('Return Date'),
            ]);
    }
}
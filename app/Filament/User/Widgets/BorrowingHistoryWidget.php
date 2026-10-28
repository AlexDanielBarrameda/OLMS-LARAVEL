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
    protected static ?int $sort = 3;

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
                // Fee refunds for rejected or cancelled paid requests
                Tables\Columns\TextColumn::make('refund_status')
                    ->label('Refund')
                    ->badge()
                    ->formatStateUsing(fn (?string $state, Transaction $record) => match ($state) {
                        Transaction::REFUNDED => 'Refunded '.$record->feeLabel(),
                        Transaction::REFUND_PENDING => 'Refund pending · '.$record->feeLabel(),
                        Transaction::REFUND_NOT_PAID => 'No refund · payment not found',
                        default => null,
                    })
                    ->color(fn (?string $state) => match ($state) {
                        Transaction::REFUNDED => 'success',
                        Transaction::REFUND_NOT_PAID => 'danger',
                        default => 'warning',
                    })
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('borrow_date')
                    ->date()
                    ->label('Borrow Date'),
                // Only set once staff check the book in; on loan (or never lent) shows a dash
                Tables\Columns\TextColumn::make('return_date')
                    ->date()
                    ->label('Return Date')
                    ->placeholder('—'),
            ]);
    }
}
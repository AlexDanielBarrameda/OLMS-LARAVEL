<?php

namespace App\Filament\Resources\BookRequests\Tables;

use App\Enums\BookRequestStatus;
use App\Filament\Resources\Books\BookResource;
use App\Models\Book;
use App\Models\BookRequest;
use App\Services\LibraryNotifications;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // Waiting for review first, newest within each group
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->latest())
            ->columns([
                TextColumn::make('title')
                    ->description(fn (BookRequest $record): string => $record->author)
                    ->searchable(['title', 'author'])
                    ->wrap(),
                TextColumn::make('reason_for_request')
                    ->label('Reason')
                    ->limit(80)
                    ->tooltip(fn (BookRequest $record): string => $record->reason_for_request)
                    ->wrap(),
                TextColumn::make('requester.name')
                    ->label('Requested by')
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('book.status')
                    ->label('Catalog')
                    ->badge()
                    ->color(fn (?string $state): string => $state === Book::STATUS_ON_ORDER ? 'info' : 'success')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->iconPosition('after')
                    ->url(fn (BookRequest $record): ?string => $record->book ? BookResource::getUrl('edit', ['record' => $record->book]) : null)
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Requested')
                    ->since()
                    ->dateTimeTooltip()
                    ->sortable(),
                TextColumn::make('reviewed_at')
                    ->label('Reviewed')
                    ->since()
                    ->dateTimeTooltip()
                    ->description(fn (BookRequest $record): ?string => $record->reviewer ? "by {$record->reviewer->name}" : null)
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(BookRequestStatus::class),
            ])
            ->actions([
                static::reviewAction('approve', BookRequestStatus::Approved),
                static::reviewAction('reject', BookRequestStatus::Rejected),
                EditAction::make(),
                DeleteAction::make()
                    ->label(fn (BookRequest $record): string => $record->requested_by === auth()->id() ? 'Withdraw' : 'Delete'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->emptyStateHeading('No book requests yet')
            ->emptyStateDescription(fn (): string => auth()->user()?->isLibrarian()
                ? 'Ask for a title the library should own, and an admin will review it.'
                : 'Requests from librarians will show up here.');
    }

    protected static function reviewAction(string $name, BookRequestStatus $decision): Action
    {
        $approving = $decision === BookRequestStatus::Approved;

        return Action::make($name)
            ->label($approving ? 'Approve' : 'Reject')
            ->icon($approving ? 'heroicon-m-check' : 'heroicon-m-x-mark')
            ->color($approving ? 'success' : 'danger')
            ->visible(fn (BookRequest $record): bool => auth()->user()?->can('review', $record) ?? false)
            ->requiresConfirmation()
            ->modalHeading(fn (BookRequest $record): string => ($approving ? 'Approve' : 'Reject')." \"{$record->title}\"?")
            ->modalDescription(fn (BookRequest $record): string => $approving
                ? "It will be added to the catalog as \"On Order\" with 0 copies, and {$record->requester?->name} will be notified."
                : "{$record->requester?->name} will be notified.")
            ->modalSubmitActionLabel($approving ? 'Approve' : 'Reject')
            ->action(function (BookRequest $record) use ($decision, $approving): void {
                abort_unless(auth()->user()?->can('review', $record), 403);

                $approving
                    ? $record->approve(auth()->user())
                    : $record->review($decision, auth()->user());

                app(LibraryNotifications::class)->bookRequestDecided($record);

                Notification::make()
                    ->title('Request '.($approving ? 'approved' : 'rejected'))
                    ->success()
                    ->send();
            });
    }
}

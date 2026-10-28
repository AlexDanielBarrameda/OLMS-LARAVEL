<?php

namespace App\Filament\Resources\ActivityLogs\Tables;

use App\Filament\Resources\ActivityLogs\ActivityPresenter;
use App\Models\Transaction;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Spatie\Activitylog\Models\Activity;

// Shared by the Activity Log page and the per-user Activity tab ($withCauser = false there, since it's always that user)
class ActivityLogsTable
{
    public static function configure(Table $table, bool $withCauser = true): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'causer',
                'subject' => fn (MorphTo $morph) => $morph->morphWith([Transaction::class => ['user', 'book']]),
            ]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('causer.name')
                    ->label('User')
                    ->description(fn (Activity $record): ?string => $record->causer instanceof User ? $record->causer->roleLabel() : null)
                    ->placeholder('System')
                    ->visible($withCauser),
                TextColumn::make('event')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => ucfirst((string) $state))
                    ->color(fn (?string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        default => 'gray',
                    })
                    ->icon(fn (?string $state): string => match ($state) {
                        'created' => 'heroicon-m-plus-circle',
                        'updated' => 'heroicon-m-pencil-square',
                        'deleted' => 'heroicon-m-trash',
                        default => 'heroicon-m-information-circle',
                    }),
                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn (Activity $record): string => ActivityPresenter::subjectType($record))
                    ->description(fn (Activity $record): string => ActivityPresenter::subjectLabel($record))
                    ->wrap(),
                TextColumn::make('changes')
                    ->label('Changed')
                    ->state(fn (Activity $record): ?string => ActivityPresenter::changedFields($record))
                    ->color('gray')
                    ->limit(50)
                    ->wrap()
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Date & Time')
                    ->since()
                    ->dateTimeTooltip('M j, Y g:i A')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('event')
                    ->options([
                        'created' => 'Created',
                        'updated' => 'Updated',
                        'deleted' => 'Deleted',
                    ]),
                SelectFilter::make('subject_type')
                    ->label('Subject')
                    ->options(ActivityPresenter::SUBJECT_TYPES),
                SelectFilter::make('causer_id')
                    ->label('User')
                    ->options(fn (): array => User::staff()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where('causer_type', User::class)->where('causer_id', $data['value'])
                        : $query)
                    ->visible($withCauser),
            ])
            ->recordActions([
                Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->modalHeading(fn (Activity $record): string => ActivityPresenter::subjectType($record).' '.$record->event)
                    ->modalDescription(fn (Activity $record): string => ActivityPresenter::subjectLabel($record))
                    ->modalContent(fn (Activity $record) => view('filament.activity-log.details', [
                        'activity' => $record,
                        'changes' => ActivityPresenter::changes($record),
                    ]))
                    ->modalWidth('lg')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
            ])
            ->emptyStateIcon('heroicon-o-queue-list')
            ->emptyStateHeading('No activity yet')
            ->emptyStateDescription('Changes to books, transactions and book requests will appear here.');
    }
}

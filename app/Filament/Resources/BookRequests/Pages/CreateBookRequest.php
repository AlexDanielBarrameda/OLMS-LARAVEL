<?php

namespace App\Filament\Resources\BookRequests\Pages;

use App\Enums\BookRequestStatus;
use App\Filament\Resources\BookRequests\BookRequestResource;
use App\Services\LibraryNotifications;
use Filament\Resources\Pages\CreateRecord;

class CreateBookRequest extends CreateRecord
{
    protected static string $resource = BookRequestResource::class;

    protected static ?string $title = 'Request a book';

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [
            ...$data,
            'requested_by' => auth()->id(),
            'status' => BookRequestStatus::Pending,
        ];
    }

    protected function afterCreate(): void
    {
        app(LibraryNotifications::class)->bookRequested($this->record->load('requester'));
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Request sent to the admin';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}

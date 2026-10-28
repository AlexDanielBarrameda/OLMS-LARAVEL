<?php

namespace App\Filament\Resources\Transactions\Pages;

use App\Enums\TransactionStatus;
use App\Filament\Resources\Transactions\TransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTransaction extends CreateRecord
{
    protected static string $resource = TransactionResource::class;

    // A loan staff create here is a desk loan: the student has the book now (takes a copy off the shelf).
    // Set on the server, since the status field is read-only.
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] = TransactionStatus::Approved;

        return $data;
    }
}

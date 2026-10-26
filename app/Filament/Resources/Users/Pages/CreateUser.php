<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    // Tells Filament where to go after clicking "Create"
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

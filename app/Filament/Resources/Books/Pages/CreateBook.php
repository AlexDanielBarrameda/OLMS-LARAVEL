<?php

namespace App\Filament\Resources\Books\Pages;

use App\Filament\Resources\Books\BookResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateBook extends CreateRecord
{
    // Keep just this one!
    protected static string $resource = BookResource::class;

    // The method to change the redirect behavior
    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
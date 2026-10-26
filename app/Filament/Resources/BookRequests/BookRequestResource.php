<?php

namespace App\Filament\Resources\BookRequests;

use App\Filament\Resources\BookRequests\Pages\CreateBookRequest;
use App\Filament\Resources\BookRequests\Pages\EditBookRequest;
use App\Filament\Resources\BookRequests\Pages\ListBookRequests;
use App\Filament\Resources\BookRequests\Schemas\BookRequestForm;
use App\Filament\Resources\BookRequests\Tables\BookRequestsTable;
use App\Models\BookRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class BookRequestResource extends Resource
{
    protected static ?string $model = BookRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Book Requests';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'title';

    // Super admins see every request; librarians see their own
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['requester', 'reviewer']);

        return auth()->user()?->isSuperAdmin()
            ? $query
            : $query->where('requested_by', auth()->id());
    }

    // Requests waiting for this person: all pending ones for a super admin, their own pending ones for a librarian
    public static function getNavigationBadge(): ?string
    {
        $pending = static::getEloquentQuery()->where('status', 'pending')->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return auth()->user()?->isSuperAdmin() ? 'Waiting for your review' : 'Waiting for review';
    }

    public static function form(Schema $schema): Schema
    {
        return BookRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return BookRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBookRequests::route('/'),
            'create' => CreateBookRequest::route('/create'),
            'edit' => EditBookRequest::route('/{record}/edit'),
        ];
    }
}

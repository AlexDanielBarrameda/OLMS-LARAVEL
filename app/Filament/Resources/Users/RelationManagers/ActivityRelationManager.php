<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\ActivityLogs\Tables\ActivityLogsTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

// "Activity" tab on a user's page: the activity-log entries this user caused (via Spatie's CausesActivity::actions())
class ActivityRelationManager extends RelationManager
{
    protected static string $relationship = 'actions';

    protected static ?string $title = 'Activity';

    protected static string|\BackedEnum|null $icon = 'heroicon-o-queue-list';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return ActivityLogsTable::configure($table, withCauser: false)
            ->heading('Recent activity');
    }
}

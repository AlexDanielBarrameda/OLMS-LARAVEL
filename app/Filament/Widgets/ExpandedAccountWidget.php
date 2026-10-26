<?php

namespace App\Filament\Widgets;

use Filament\Widgets\AccountWidget;

class ExpandedAccountWidget extends AccountWidget
{
    // This forces the widget to span the entire grid!
    protected int | string | array $columnSpan = 'full';
}
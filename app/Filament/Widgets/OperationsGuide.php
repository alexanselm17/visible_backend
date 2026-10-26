<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class OperationsGuide extends Widget
{
    protected static string $view = 'filament.widgets.operations-guide';

    protected int|string|array $columnSpan = 'full';
}

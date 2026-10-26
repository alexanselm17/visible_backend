<?php

namespace App\Filament\Support;

use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

trait NotifiesWorkflowErrors
{
    public function exceptionNotifiesWorkflowErrors($e, $stopPropagation): void
    {
        if ($e instanceof ValidationException) {
            Notification::make()->title('Please check this operation')
                ->body(collect($e->errors())->flatten()->unique()->implode(' '))
                ->danger()->send();
        }
    }
}

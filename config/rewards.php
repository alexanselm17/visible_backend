<?php

return [
    'timezone' => env('REWARD_TIMEZONE', 'Africa/Nairobi'),
    'default_frequency' => env('REWARD_DEFAULT_FREQUENCY', 'monthly'),
    'closure_grace_hours' => (int) env('REWARD_CLOSURE_GRACE_HOURS', 24),
];

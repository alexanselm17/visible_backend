<?php

namespace App\Providers;

use App\Models\Screenshots;
use App\Observers\ScreenshotObserver;
use Illuminate\Support\ServiceProvider;

class ObserverServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Screenshots::observe(ScreenshotObserver::class);
    }
}

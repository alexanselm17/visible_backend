<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The path to the "home" route for your application.
     *
     * Typically, users are redirected here after authentication.
     *
     * @var string
     */
    public const HOME = '/home';

    /**
     * Define your route model bindings, pattern filters, and other route configuration.
     *
     * @return void
     */
    public function boot()
    {
        $this->configureRateLimiting();

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Configure the rate limiters for the application.
     *
     * @return void
     */
    protected function configureRateLimiting()
    {
        RateLimiter::for('ncba-receipt-claims', function (Request $request) {
            $user = $request->user()?->id ?: $request->ip();

            return [Limit::perMinute(5)->by('ncba-claim-minute:'.$user),
                Limit::perDay(30)->by('ncba-claim-day:'.$user),
                Limit::perMinute(30)->by('ncba-claim-ip:'.$request->ip())];
        });
        RateLimiter::for('ncba-notifications', function (Request $request) {
            return Limit::perMinute(240)->by($request->ip())
                ->response(fn (Request $request, array $headers) => response()->json([
                    'ResultCode' => '1', 'ResultDesc' => 'Too many notifications. Please retry.',
                ], 429, $headers));
        });
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });
    }
}

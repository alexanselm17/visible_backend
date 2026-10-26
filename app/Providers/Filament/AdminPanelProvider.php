<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\CustomLogin;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Visible Workspace')
            ->brandLogoHeight('2rem')
            ->maxContentWidth('full')
            ->renderHook('panels::head.end', fn () => view('filament.theme'))
            ->navigationGroups([
                \Filament\Navigation\NavigationGroup::make('Campaigns'),
                \Filament\Navigation\NavigationGroup::make('Members')->collapsed(),
                \Filament\Navigation\NavigationGroup::make('Tokens')->collapsed(),
                \Filament\Navigation\NavigationGroup::make('Performance earnings')->collapsed(),
                \Filament\Navigation\NavigationGroup::make('Trust & verification')->collapsed(),
                \Filament\Navigation\NavigationGroup::make('Settings')->collapsed(),
                \Filament\Navigation\NavigationGroup::make('Legacy records')->collapsed(),
            ])
            ->sidebarCollapsibleOnDesktop()
            ->login(CustomLogin::class)
            ->colors([
                'primary' => Color::Teal,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,

            ])
            ->widgets([
                \App\Filament\Widgets\OperationsGuide::class,
                \App\Filament\Widgets\OperationsOverview::class,
                \App\Filament\Widgets\ViewsChart::class,
                \App\Filament\Widgets\ScreenshotsSubmissionChart::class,
                \App\Filament\Widgets\TokenRevenueChart::class,
                \App\Filament\Widgets\RewardsChart::class,
                \App\Filament\Widgets\RecentActivityWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}

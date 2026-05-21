<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\ForgotPassword;
use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Auth\RegisterCompany;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\ApplyTenantScopes;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class CompanyPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('company')
            ->path('company')
            ->login(Login::class)
            ->spa()
            ->databaseTransactions()
            ->unsavedChangesAlerts()
            ->registration(RegisterCompany::class)
            ->passwordReset(ForgotPassword::class)
            ->profile(EditProfile::class, isSimple: false)
            ->colors([
                'primary' => Color::Amber,
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('60s')
            ->sidebarCollapsibleOnDesktop()
            ->sidebarWidth(Width::FitContent->value)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                AccountWidget::class,
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
                ApplyTenantScopes::class,
                Authenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => new HtmlString('
                    <div
                        x-data="{
                            playSuccess() {
                                let ctx = new (window.AudioContext || window.webkitAudioContext)();
                                let osc = ctx.createOscillator();
                                let gain = ctx.createGain();
                                osc.connect(gain);
                                gain.connect(ctx.destination);
                                osc.type = \'sine\';
                                osc.frequency.setValueAtTime(800, ctx.currentTime);
                                gain.gain.setValueAtTime(0.1, ctx.currentTime);
                                osc.start();
                                gain.gain.exponentialRampToValueAtTime(0.00001, ctx.currentTime + 0.1);
                                osc.stop(ctx.currentTime + 0.1);
                            },
                            playError() {
                                let ctx = new (window.AudioContext || window.webkitAudioContext)();
                                let osc = ctx.createOscillator();
                                let gain = ctx.createGain();
                                osc.connect(gain);
                                gain.connect(ctx.destination);
                                osc.type = \'sawtooth\';
                                osc.frequency.setValueAtTime(150, ctx.currentTime);
                                gain.gain.setValueAtTime(0.1, ctx.currentTime);
                                osc.start();
                                gain.gain.exponentialRampToValueAtTime(0.00001, ctx.currentTime + 0.3);
                                osc.stop(ctx.currentTime + 0.3);
                            }
                        }"
                        x-on:play-sound-success.window="playSuccess()"
                        x-on:play-sound-error.window="playError()"
                    ></div>
                    <script>
                        document.addEventListener("keydown", function(event) {
                            if (event.key === "Enter") {
                                let target = event.target;
                                if (target.tagName !== "TEXTAREA" && target.closest("form")) {
                                    event.preventDefault();
                                    target.blur();
                                }
                            }
                        });
                    </script>
                ')
            );
    }
}

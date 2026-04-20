<?php

namespace Tests\Feature\Middleware;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class AppLocaleMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/test-locale', function () {
            return app()->getLocale();
        })->middleware('web'); // web group includes AppLocale
    }

    public function test_it_sets_app_locale_from_session(): void
    {
        Session::put('locale', 'ar_EG');

        $response = $this->get('/test-locale');

        $response->assertStatus(200);
        $this->assertEquals('ar', app()->getLocale());
        $response->assertSee('ar');
    }

    public function test_it_sets_app_locale_from_custom_header(): void
    {
        Session::forget(['locale', 'language']);

        $response = $this->get('/test-locale', [
            'App-Language' => 'en-US',
        ]);

        $response->assertStatus(200);
        $this->assertEquals('en', app()->getLocale());
    }

    public function test_it_ignores_accept_language_header(): void
    {
        Session::forget(['locale', 'language']);

        $response = $this->get('/test-locale', [
            'Accept-Language' => 'en-US',
        ]);

        $this->assertEquals('ar', app()->getLocale());
    }

    public function test_it_handles_invalid_session_locale_by_falling_back(): void
    {
        Session::put('locale', 'invalid_LOCALE');

        $response = $this->get('/test-locale');

        $response->assertStatus(200);
        $this->assertEquals(config('app.locale'), app()->getLocale());
    }
}

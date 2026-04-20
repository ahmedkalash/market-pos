<?php

namespace Tests\Feature\Middleware;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class LocalePersistenceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Route::get('/test-locale-persistence', function () {
            return app()->getLocale();
        })->middleware('web');
    }

    /**
     * Test that the application defaults to Arabic even if the browser sends an English Accept-Language header.
     */
    public function test_it_ignores_browser_accept_language_header(): void
    {
        Session::forget(['locale', 'language']);

        // Send English header, but should still get Arabic
        $response = $this->get('/test-locale-persistence', [
            'Accept-Language' => 'en-US,en;q=0.9',
        ]);

        $response->assertStatus(200);
        
        // Final expectation: getLocale() should be 'ar' because 'ar_EG' is the fallback and it's truncated to 'ar'
        $this->assertEquals('ar', app()->getLocale());
    }

    /**
     * Test that manual session selection still works.
     */
    public function test_it_still_respects_manual_session_locale(): void
    {
        Session::put('locale', 'en_US');

        $response = $this->get('/test-locale-persistence');

        $response->assertStatus(200);
        $this->assertEquals('en', app()->getLocale());
    }
}

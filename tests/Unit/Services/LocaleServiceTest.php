<?php

namespace Tests\Unit\Services;

use App\Services\LocaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class LocaleServiceTest extends TestCase
{
    private LocaleService $localeService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->localeService = new LocaleService;

        // Mock supported locales
        Config::set('app.supported_locales', [
            'en_US' => ['name' => 'English'],
            'ar_EG' => ['name' => 'Arabic'],
        ]);
        Config::set('app.locale', 'en_US');
    }

    public function test_it_resolves_locale_from_session(): void
    {
        Session::put('locale', 'ar_EG');
        $request = Request::create('/test', 'GET');
        $request->setLaravelSession(Session::driver());

        $this->localeService->setCurrentLocaleFromRequest($request);

        $this->assertEquals('ar', app()->getLocale());
        $this->assertEquals('ar_EG', Session::get('locale'));
    }

    public function test_it_resolves_locale_from_header_if_session_empty(): void
    {
        Session::forget('locale');
        $request = Request::create('/test', 'GET');
        $request->headers->set('Accept-Language', 'ar-EG');
        $request->setLaravelSession(Session::driver());

        $this->localeService->setCurrentLocaleFromRequest($request);

        $this->assertEquals('ar', app()->getLocale());
        $this->assertEquals('ar_EG', Session::get('locale'));
    }

    public function test_it_falls_back_to_config_locale(): void
    {
        Session::forget('locale');
        $request = Request::create('/test', 'GET');
        $request->setLaravelSession(Session::driver());
        Config::set('app.locale', 'en');
        Config::set('app.full_locale', 'en_US');

        $this->localeService->setCurrentLocaleFromRequest($request);

        $this->assertEquals('en', app()->getLocale());
        $this->assertEquals('en_US', Session::get('locale'));
    }

    public function test_it_persists_locale_to_session(): void
    {
        $this->localeService->setCurrentLocale('ar_EG');

        $this->assertEquals('ar_EG', Session::get('locale'));
        $this->assertEquals('ar', app()->getLocale());
    }
}

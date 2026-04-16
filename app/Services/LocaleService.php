<?php

namespace App\Services;

use App\DTOs\LocaleDTO;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class LocaleService
{
    /**
     * @param  string  $fullLocale  full locale format like 'en-US', 'en_US'
     */
    public function setCurrentLocale(string $fullLocale): void
    {
        $fullLocale = $this->validated($fullLocale);

        $localeDto = LocaleDTO::fromString($fullLocale);

        App::setLocale($localeDto->language);
        Carbon::setLocale($fullLocale);

        Session::put('locale', $fullLocale);
        Session::put('locale_region', $localeDto->region);
    }

    /**
     * Extract the primary locale from an Accept-Language header value.
     */
    private function parseAcceptLanguage(string $header): string
    {
        $primary = explode(',', $header)[0];
        $primary = explode(';', $primary)[0];

        return trim($primary);
    }

    /**
     * Validate and normalize a locale string against our supported locales.
     */
    private function validated(string $locale): string
    {
        $locale = str_replace('-', '_', trim($locale));

        $supported = array_keys(static::getSupportedLocales());

        if (in_array($locale, $supported, true)) {
            return $locale;
        }

        $language = LocaleDTO::fromString($locale)->language;

        foreach ($supported as $supportedLocale) {
            if (LocaleDTO::fromString($supportedLocale)->language === $language) {
                return $supportedLocale;
            }
        }

        return config('app.full_locale', 'ar_EG');
    }

    /**
     * Determine the locale from the request using the priority chain:
     * App-Language header -> Session (language/locale) -> Accept-Language header -> Config fallback
     */
    private function resolveLocale(Request $request): string
    {
        if ($request->hasHeader('App-Language')) {
            $candidate = $request->header('App-Language');
        } elseif ($request->hasSession() && Session::has('language')) {
            $candidate = Session::get('language');
        } elseif ($request->hasSession() && Session::has('locale')) {
            $candidate = Session::get('locale');
        } elseif ($request->hasHeader('Accept-Language')) {
            $candidate = $this->parseAcceptLanguage($request->header('Accept-Language'));
        } else {
            $candidate = config('app.locale', 'ar');
        }

        return $this->validated($candidate);
    }

    public function setCurrentLocaleFromRequest(Request $request): void
    {
        $fullLocale = $this->resolveLocale($request);
        $this->setCurrentLocale($fullLocale);
    }

    /**
     * Get current full locale lang code like 'en_US', 'ar_EG'.
     */
    public static function getCurrentFullLocale(): string
    {
        return session('locale', config('app.full_locale', 'ar_EG'));
    }

    /**
     * Get current locale lang code like 'en', 'ar'.
     */
    public static function getCurrentLocale(): string
    {
        return App::getLocale();
    }

    public static function getCurrentLocaleDir(): string
    {
        $current_locale = static::getCurrentFullLocale();
        $supported = static::getSupportedLocales();

        return $supported[$current_locale]['dir'] ?? 'ltr';
    }

    public static function getCurrentLocaleLangCode(): string
    {
        $current_locale = static::getCurrentFullLocale();
        $supported = static::getSupportedLocales();

        return $supported[$current_locale]['language'] ?? 'ar';
    }

    public static function getCurrentLocaleRegion(): string
    {
        $current_locale = static::getCurrentFullLocale();
        $supported = static::getSupportedLocales();

        return $supported[$current_locale]['region'] ?? 'EG';
    }

    public static function getCurrentLocaleName(): string
    {
        $current_locale = static::getCurrentFullLocale();
        $supported = static::getSupportedLocales();

        return $supported[$current_locale]['name'] ?? 'العربية';
    }

    /**
     * @return array supported locales array
     */
    public static function getSupportedLocales(): array
    {
        return config('app.supported_locales', []);
    }
}

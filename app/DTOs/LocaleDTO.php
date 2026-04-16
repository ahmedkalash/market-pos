<?php

namespace App\DTOs;

readonly class LocaleDTO
{
    public function __construct(
        public string $language,
        public ?string $region = null
    ) {}

    /**
     * Create a LocaleDTO from a locale string.
     *
     * Handles both underscore (POSIX) and hyphen (BCP 47) separators.
     */
    public static function fromString(string $locale): self
    {
        $parts = preg_split('/[_\-]/', $locale, 2);

        return new self(
            strtolower($parts[0]),                              // Language: always lowercase ("ar")
            isset($parts[1]) ? strtoupper($parts[1]) : null    // Region: always uppercase ("SA") or null
        );
    }
}

<?php

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Illuminate\View\ComponentAttributeBag;

if (! function_exists('badge')) {
    function badge(string $text, array $attributes = ['color' => 'primary',  'size' => 'xl'], bool $asHtmlString = false): HtmlString|string
    {
        // We pass the entire array natively using Laravel's ComponentAttributeBag
        $renderedBadge = Blade::render(
            '<x-filament::badge :attributes="$badgeAttributes">{{ $text }}</x-filament::badge>',
            [
                'text' => $text,
                'badgeAttributes' => new ComponentAttributeBag($attributes),
            ]
        );

        return $asHtmlString ? new HtmlString($renderedBadge) : $renderedBadge;
    }
}

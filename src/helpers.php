<?php

use Carbon\Carbon;
use Illuminate\Support\HtmlString;
use Jenssegers\Date\Date;

if (!function_exists('localize')) {
    function localize(string $locale) {
        if (strlen($locale) !== 2 && strlen($locale) !== 5) {
            throw new InvalidArgumentException("Localize accepts a locale of exactly 2 or 5 characters, got {$locale}");
        }

        $parts = str_replace('_', '-', explode('-', $locale));
        $locale = $parts[1] ?? $locale;

        $locale = strtolower($locale);

        app()->setLocale($locale);
        Carbon::setLocale($locale);
        setlocale(LC_TIME, $locale . '_' . ($locale == 'en' ? 'US' : strtoupper($locale)) . '.utf-8');
    }
}

if (!function_exists('browsersync')) {
    function browsersync() {
        return new HtmlString(view('component-laravel::browsersync')->render());
    }
}

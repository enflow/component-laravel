<?php

use Carbon\Carbon;
use Illuminate\Support\HtmlString;
use Jenssegers\Date\Date;

if (!function_exists('localize')) {
    function localize(string $locale) {
        if (strlen($locale) !== 2) {
            throw new InvalidArgumentException("Localize accepts a locale of exactly 2 characters");
        }

        $locale = strtolower($locale);

        app()->setLocale($locale);
        Carbon::setLocale($locale);
        Date::setLocale($locale);
        setlocale(LC_TIME, $locale . '_' . ($locale == 'en' ? 'US' : strtoupper($locale)) . '.utf-8');
    }
}

if (!function_exists('bugsnag_js')) {
    function bugsnag_js() {
        return new HtmlString(view('component-laravel::bugsnag-js')->render());
    }
}

if (!function_exists('browsersync')) {
    function browsersync() {
        return new HtmlString(view('component-laravel::browsersync')->render());
    }
}

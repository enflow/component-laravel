<?php

use Illuminate\Support\Facades\Route;

if (config('laravel.security_txt', true)) {
    Route::get('/.well-known/security.txt', fn() => response()->view('component-laravel::security-txt', [], 200, [
        'Content-Type' => 'text/plain',
    ]));
}
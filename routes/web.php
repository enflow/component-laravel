<?php

use Illuminate\Support\Facades\Route;

if (config('laravel.security_txt', true)) {
    Route::redirect('security.txt', '/.well-known/security.txt');

    Route::get('.well-known/security.txt', fn() => response()->view('component-laravel::security-txt', [], 200, [
        'Content-Type' => 'text/plain',
    ]));
}
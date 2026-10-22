<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| GCEGO Runtime
|--------------------------------------------------------------------------
|
| Publish with "php artisan vendor:publish --tag=gcego-runtime-config".
|
*/

return [

    'rendering' => [
        // Throw on the first rendering failure (true), or report it and show an
        // accessible "could not be displayed" placeholder for that component (false).
        'strict' => env('GCEGO_RUNTIME_STRICT', true),

        // Validate definitions (component schema + blueprint composition) before rendering.
        'validate' => true,

        // Maximum component nesting depth rendered (guards runaway recursion).
        'max_depth' => 64,
    ],

    'livewire' => [
        // Register the <livewire:gcego-blueprint blueprint="..."/> host.
        'enabled' => true,
        'alias' => 'gcego-blueprint',
    ],

    'authorization' => [
        // Gate ability checked before rendering through the Livewire host or
        // <x-gcego-runtime::render>. Laravel denies undefined abilities, so
        // nothing renders publicly until you define it (fails closed):
        // Gate::define('gcego-runtime.render', fn (?User $user, string $identity) => ...);
        'ability' => 'gcego-runtime.render',
    ],

];

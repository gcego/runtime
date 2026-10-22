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

    'execution' => [
        // Gate ability every executed operation must pass, checked as the
        // context's user. Laravel denies undefined abilities, so no operation
        // runs until you define it (fails closed):
        // Gate::define('gcego-runtime.execute', fn (?User $user, ExecutionRequest $request) => ...);
        'ability' => 'gcego-runtime.execute',

        // ExecutionMiddleware classes wrapped around every handler, outermost
        // first. They run after authorization and validation.
        'middleware' => [],

        // Maximum nesting of operations executed from inside handlers.
        'max_depth' => 8,
    ],

];

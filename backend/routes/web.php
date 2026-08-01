<?php

use Illuminate\Support\Facades\Route;

/*
|---------------------------------------------------------------------------
| Web Routes
|---------------------------------------------------------------------------
|
| This application is an API-only backend; the administrative interface is a
| separate React SPA. The root route exists purely so hitting the host in a
| browser is informative rather than a 404.
|
*/

Route::get('/', fn (): array => [
    'name' => config('app.name'),
    'api' => url('/api/v1'),
    'health' => url('/up'),
    'docs' => 'See README.md for endpoint documentation.',
]);

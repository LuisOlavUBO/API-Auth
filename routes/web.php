<?php

use Illuminate\Support\Facades\Route;

// This is an API: the root leads to the docs instead of Laravel's welcome page.
Route::redirect('/', '/docs/swagger');

Route::view('docs/swagger', 'docs.swagger');

Route::get('docs/openapi.yaml', function () {
    return response()->file(base_path('docs/openapi/openapi.yaml'), [
        'Content-Type' => 'application/yaml; charset=UTF-8',
    ]);
});

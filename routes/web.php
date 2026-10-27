<?php

use App\Http\ApiPrefix;
use Illuminate\Support\Facades\Route;

// This is an API: the root leads to the docs. They live under the API prefix so that
// ubo-nginx-proxy routes them to this container; these shortcuts are for direct access.
Route::redirect('/', '/'.ApiPrefix::BASE.'/docs/swagger');
Route::redirect('docs/swagger', '/'.ApiPrefix::BASE.'/docs/swagger');

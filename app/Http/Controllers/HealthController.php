<?php

namespace App\Http\Controllers;

use App\Services\DatabaseHealth;
use Illuminate\Http\JsonResponse;

class HealthController extends Controller
{
    /**
     * GET /api/v1/salud, without authentication, for orchestrators and load
     * balancers. Same shape as the other UBO APIs.
     */
    public function __invoke(DatabaseHealth $database): JsonResponse
    {
        if (! $database->isAvailable()) {
            return response()->json(['estado' => 'ERROR', 'base_datos' => 'NO_DISPONIBLE'], 503);
        }

        return response()->json(['estado' => 'OK', 'base_datos' => 'OK']);
    }
}

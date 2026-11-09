<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\OAuthTokenService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * Lets a service (API-IAM) close every session of a user when their access changes:
 * it revokes all their tokens and deletes their IAM sessions. Revoking twice is fine.
 */
class SessionRevocationController extends Controller
{
    public function __invoke(string $uuid, OAuthTokenService $tokens): Response
    {
        $user = User::where('uuid', $uuid)->firstOrFail();

        $tokens->revokeAllFor($user);

        DB::connection(config('session.connection'))
            ->table(config('session.table'))
            ->where('user_id', $user->getKey())
            ->delete();

        return response()->noContent();
    }
}

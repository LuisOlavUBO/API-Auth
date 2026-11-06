<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Services\OAuthTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(protected OAuthTokenService $tokens) {}

    /**
     * Exchange email/password for an access/refresh token pair.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        return response()->json($this->tokens->issueForCredentials(
            $request->input('email'),
            $request->input('password'),
            $request->input('scope'),
        ));
    }

    /**
     * Rotate a refresh token into a new token pair.
     */
    public function refresh(Request $request): JsonResponse
    {
        $request->validate([
            'refresh_token' => ['required', 'string'],
            'scope' => ['nullable', 'string'],
        ]);

        return response()->json($this->tokens->refresh(
            $request->input('refresh_token'),
            $request->input('scope'),
        ));
    }

    /**
     * Return the user that owns the current access token.
     */
    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    /**
     * Revoke the current access token and its refresh token.
     */
    public function logout(Request $request): JsonResponse
    {
        $this->tokens->revoke($request->user()->token()->oauth_access_token_id);

        return response()->json(['message' => 'Session closed.']);
    }

    /**
     * Revoke every token the user holds (all devices).
     */
    public function logoutAll(Request $request): JsonResponse
    {
        $this->tokens->revokeAllFor($request->user());

        return response()->json(['message' => 'All sessions closed.']);
    }
}

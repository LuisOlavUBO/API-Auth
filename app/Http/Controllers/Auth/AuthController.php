<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Role;
use App\Models\User;
use App\Services\OAuthTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(protected OAuthTokenService $tokens) {}

    /**
     * Create a user account and return its first token pair.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $user = new User($request->validated());
        $user->role()->associate(Role::firstWhere('name', config('oauth.default_role')));
        $user->save();

        $token = $this->tokens->issueForCredentials($user->email, $request->input('password'));

        return response()->json([
            'user' => new UserResource($user),
            ...$token,
        ], 201);
    }

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
        $request->user()->tokens()->where('revoked', false)->pluck('id')
            ->each(fn (string $id) => $this->tokens->revoke($id));

        return response()->json(['message' => 'All sessions closed.']);
    }
}

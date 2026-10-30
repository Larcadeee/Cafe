<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\GoogleCallbackRequest;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RefreshTokenRequest;
use App\Http\Requests\SignupRequest;
use App\Services\AuthService;
use App\Services\OAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected AuthService $authService;
    protected OAuthService $oauthService;

    public function __construct(AuthService $authService, OAuthService $oauthService)
    {
        $this->authService = $authService;
        $this->oauthService = $oauthService;
    }

    /**
     * Login user with email and password.
     *
     * @param LoginRequest $request
     * @return JsonResponse
     */
    public function login(LoginRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->login(
                $request->validated('email'),
                $request->validated('password')
            );

            return response()->json([
                'success' => true,
                'message' => 'Login successful.',
                'data' => $result,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Login failed.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Register a new user.
     *
     * @param SignupRequest $request
     * @return JsonResponse
     */
    public function signup(SignupRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->signup(
                $request->validated('name'),
                $request->validated('email'),
                $request->validated('password')
            );

            return response()->json([
                'success' => true,
                'message' => 'Registration successful.',
                'data' => $result,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Registration failed.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Get Google OAuth authorization URL.
     *
     * @return JsonResponse
     */
    public function googleAuthUrl(): JsonResponse
    {
        $state = bin2hex(random_bytes(32));
        $authUrl = $this->oauthService->getGoogleAuthUrl($state);

        return response()->json([
            'success' => true,
            'data' => [
                'auth_url' => $authUrl,
                'state' => $state,
            ],
        ], 200);
    }

    /**
     * Handle Google OAuth callback.
     *
     * @param GoogleCallbackRequest $request
     * @return JsonResponse
     */
    public function googleCallback(GoogleCallbackRequest $request): JsonResponse
    {
        try {
            $result = $this->oauthService->handleGoogleCallback(
                $request->validated('code')
            );

            return response()->json([
                'success' => true,
                'message' => 'Google authentication successful.',
                'data' => $result,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Google authentication failed.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Refresh access token using refresh token.
     *
     * @param RefreshTokenRequest $request
     * @return JsonResponse
     */
    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        try {
            $result = $this->authService->refreshAccessToken(
                $request->validated('refresh_token')
            );

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully.',
                'data' => $result,
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Token refresh failed.',
                'errors' => $e->errors(),
            ], 422);
        }
    }

    /**
     * Logout user by revoking refresh token.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function logout(Request $request): JsonResponse
    {
        $request->validate([
            'refresh_token' => ['required', 'string'],
        ]);

        $success = $this->authService->logout($request->input('refresh_token'));

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'Logout successful.',
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Logout failed.',
        ], 422);
    }

    /**
     * Get current authenticated user data.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'User not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user->only(['id', 'name', 'email', 'role', 'profile_picture_url', 'is_active']),
        ], 200);
    }
}

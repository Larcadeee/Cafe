<?php

namespace App\Services;

use App\Models\OAuthProvider;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class OAuthService
{
    protected TokenService $tokenService;
    protected string $clientId;
    protected string $clientSecret;
    protected string $redirectUri;

    public function __construct(TokenService $tokenService)
    {
        $this->tokenService = $tokenService;
        $this->clientId = config('oauth.google.client_id');
        $this->clientSecret = config('oauth.google.client_secret');
        $this->redirectUri = config('oauth.google.redirect_uri');
    }

    /**
     * Generate Google OAuth authorization URL.
     *
     * @param string $state CSRF state parameter
     * @return string Authorization URL
     */
    public function getGoogleAuthUrl(string $state): string
    {
        $params = [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', config('oauth.google.scopes')),
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }

    /**
     * Handle Google OAuth callback - exchange authorization code for tokens.
     *
     * @param string $code Authorization code from Google
     * @return array ['access_token', 'refresh_token', 'user']
     * @throws ValidationException
     */
    public function handleGoogleCallback(string $code): array
    {
        // Exchange authorization code for access token
        $googleTokens = $this->exchangeCodeForTokens($code);

        if (!$googleTokens) {
            throw ValidationException::withMessages([
                'code' => ['Failed to exchange authorization code with Google.'],
            ]);
        }

        // Get user profile from Google
        $googleUser = $this->getGoogleUserInfo($googleTokens['access_token']);

        if (!$googleUser) {
            throw ValidationException::withMessages([
                'code' => ['Failed to fetch user profile from Google.'],
            ]);
        }

        // Find or create user
        $user = $this->findOrCreateUserFromGoogle($googleUser);

        // Store/update OAuth provider information
        $this->storeOAuthProvider($user, $googleTokens, $googleUser);

        // Generate application auth tokens
        return [
            'access_token' => $this->tokenService->generateAccessToken($user),
            'refresh_token' => $this->tokenService->generateRefreshToken($user),
            'user' => $user->only(['id', 'name', 'email', 'role', 'profile_picture_url']),
            'expires_in' => config('oauth.jwt.expiration'),
        ];
    }

    /**
     * Exchange Google authorization code for access token.
     *
     * @param string $code
     * @return array|null
     */
    protected function exchangeCodeForTokens(string $code): ?array
    {
        try {
            $response = Http::post('https://oauth2.googleapis.com/token', [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'code' => $code,
                'grant_type' => 'authorization_code',
                'redirect_uri' => $this->redirectUri,
            ]);

            if (!$response->successful()) {
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Fetch user profile from Google using access token.
     *
     * @param string $accessToken
     * @return array|null User profile data
     */
    protected function getGoogleUserInfo(string $accessToken): ?array
    {
        try {
            $response = Http::withToken($accessToken)
                ->get('https://www.googleapis.com/oauth2/v2/userinfo');

            if (!$response->successful()) {
                return null;
            }

            return $response->json();
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Find existing user by google_id or create new user from Google profile.
     *
     * @param array $googleUser
     * @return User
     */
    protected function findOrCreateUserFromGoogle(array $googleUser): User
    {
        // Try to find by google_id first
        $user = User::where('google_id', $googleUser['id'])->first();

        if ($user) {
            return $user;
        }

        // Try to find by email
        $user = User::where('email', $googleUser['email'])->first();

        if ($user) {
            // Link existing user to Google account
            $user->update(['google_id' => $googleUser['id']]);
            return $user;
        }

        // Create new user
        return User::create([
            'name' => $googleUser['name'] ?? 'User',
            'email' => $googleUser['email'],
            'google_id' => $googleUser['id'],
            'profile_picture_url' => $googleUser['picture'] ?? null,
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'customer',
        ]);
    }

    /**
     * Store or update OAuth provider information for user.
     *
     * @param User $user
     * @param array $tokens Google tokens response
     * @param array $googleUser Google user profile
     * @return void
     */
    protected function storeOAuthProvider(User $user, array $tokens, array $googleUser): void
    {
        OAuthProvider::updateOrCreate(
            [
                'user_id' => $user->id,
                'provider' => 'google',
            ],
            [
                'provider_id' => $googleUser['id'],
                'provider_token' => $tokens['access_token'],
                'provider_refresh_token' => $tokens['refresh_token'] ?? null,
                'token_expires_at' => now()->addSeconds($tokens['expires_in'] ?? 3600),
            ]
        );
    }
}

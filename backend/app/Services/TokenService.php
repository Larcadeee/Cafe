<?php

namespace App\Services;

use App\Models\OAuthToken;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

class TokenService
{
    /**
     * Generate a new access token for a user.
     *
     * @param User $user
     * @param int|null $expiresIn Seconds until expiration (default: 15 minutes)
     * @return string
     */
    public function generateAccessToken(User $user, ?int $expiresIn = null): string
    {
        $expiresIn = $expiresIn ?? config('oauth.jwt.expiration');

        $payload = [
            'iss' => config('app.url'),
            'sub' => $user->id,
            'iat' => time(),
            'exp' => time() + $expiresIn,
            'email' => $user->email,
            'name' => $user->name,
            'role' => $user->role,
        ];

        return JWT::encode($payload, $this->getSecret(), 'HS256');
    }

    /**
     * Generate a new refresh token for a user.
     *
     * @param User $user
     * @param int|null $expiresIn Seconds until expiration (default: 30 days)
     * @return string
     */
    public function generateRefreshToken(User $user, ?int $expiresIn = null): string
    {
        $expiresIn = $expiresIn ?? config('oauth.jwt.refresh_expiration');

        $payload = [
            'iss' => config('app.url'),
            'sub' => $user->id,
            'iat' => time(),
            'exp' => time() + $expiresIn,
            'type' => 'refresh',
        ];

        $refreshToken = JWT::encode($payload, $this->getSecret(), 'HS256');

        // Store the refresh token in database
        OAuthToken::create([
            'user_id' => $user->id,
            'refresh_token' => $refreshToken,
            'expires_at' => now()->addSeconds($expiresIn),
            'revoked' => false,
        ]);

        return $refreshToken;
    }

    /**
     * Validate an access token and return its payload.
     *
     * @param string $token
     * @return object|null Token payload if valid, null if invalid
     */
    public function validateAccessToken(string $token): ?object
    {
        try {
            $payload = JWT::decode($token, new Key($this->getSecret(), 'HS256'));
            return $payload;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Validate a refresh token and return its payload.
     *
     * @param string $token
     * @return object|null Token payload if valid, null if invalid
     */
    public function validateRefreshToken(string $token): ?object
    {
        try {
            $payload = JWT::decode($token, new Key($this->getSecret(), 'HS256'));

            // Check if token is stored in database and not revoked
            $storedToken = OAuthToken::where('refresh_token', $token)
                ->where('revoked', false)
                ->where('expires_at', '>', now())
                ->first();

            if (!$storedToken) {
                return null;
            }

            return $payload;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Revoke a refresh token.
     *
     * @param string $token
     * @return bool
     */
    public function revokeRefreshToken(string $token): bool
    {
        $oauthToken = OAuthToken::where('refresh_token', $token)->first();

        if (!$oauthToken) {
            return false;
        }

        return (bool) $oauthToken->update(['revoked' => true]);
    }

    /**
     * Get the JWT secret key.
     *
     * @return string
     */
    private function getSecret(): string
    {
        return config('oauth.jwt.secret');
    }

    /**
     * Extract user ID from token payload.
     *
     * @param object $payload
     * @return int|null
     */
    public function getUserIdFromPayload(object $payload): ?int
    {
        return $payload->sub ?? null;
    }
}

<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthService
{
    protected TokenService $tokenService;

    public function __construct(TokenService $tokenService)
    {
        $this->tokenService = $tokenService;
    }

    /**
     * Authenticate a user with email and password.
     *
     * @param string $email
     * @param string $password
     * @return array ['access_token', 'refresh_token', 'user']
     * @throws ValidationException
     */
    public function login(string $email, string $password): array
    {
        $user = User::where('email', $email)
            ->where('is_active', true)
            ->first();

        if (!$user || !$this->validatePassword($user, $password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $this->updateLastLogin($user);

        return [
            'access_token' => $this->tokenService->generateAccessToken($user),
            'refresh_token' => $this->tokenService->generateRefreshToken($user),
            'user' => $user->only(['id', 'name', 'email', 'role', 'profile_picture_url']),
            'expires_in' => config('oauth.jwt.expiration'),
        ];
    }

    /**
     * Register a new user with email and password.
     *
     * @param string $name
     * @param string $email
     * @param string $password
     * @return array ['access_token', 'refresh_token', 'user']
     * @throws ValidationException
     */
    public function signup(string $name, string $email, string $password): array
    {
        // Check if user already exists
        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => ['This email is already registered.'],
            ]);
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'email_verified_at' => now(),
            'is_active' => true,
            'role' => 'customer', // Default role for new users
        ]);

        $this->updateLastLogin($user);

        return [
            'access_token' => $this->tokenService->generateAccessToken($user),
            'refresh_token' => $this->tokenService->generateRefreshToken($user),
            'user' => $user->only(['id', 'name', 'email', 'role', 'profile_picture_url']),
            'expires_in' => config('oauth.jwt.expiration'),
        ];
    }

    /**
     * Validate a user's password.
     *
     * @param User $user
     * @param string $password
     * @return bool
     */
    public function validatePassword(User $user, string $password): bool
    {
        if (!$user->password) {
            // User registered via OAuth, no password set
            return false;
        }

        return Hash::check($password, $user->password);
    }

    /**
     * Update the user's last login timestamp.
     *
     * @param User $user
     * @return void
     */
    public function updateLastLogin(User $user): void
    {
        $user->update(['last_login_at' => now()]);
    }

    /**
     * Logout a user by revoking their refresh token.
     *
     * @param string $refreshToken
     * @return bool
     */
    public function logout(string $refreshToken): bool
    {
        return $this->tokenService->revokeRefreshToken($refreshToken);
    }

    /**
     * Refresh an access token using a refresh token.
     *
     * @param string $refreshToken
     * @return array ['access_token', 'user']
     * @throws ValidationException
     */
    public function refreshAccessToken(string $refreshToken): array
    {
        $payload = $this->tokenService->validateRefreshToken($refreshToken);

        if (!$payload) {
            throw ValidationException::withMessages([
                'refresh_token' => ['The refresh token is invalid or expired.'],
            ]);
        }

        $user = User::find($payload->sub);

        if (!$user || !$user->is_active) {
            throw ValidationException::withMessages([
                'refresh_token' => ['User not found or is inactive.'],
            ]);
        }

        return [
            'access_token' => $this->tokenService->generateAccessToken($user),
            'user' => $user->only(['id', 'name', 'email', 'role', 'profile_picture_url']),
            'expires_in' => config('oauth.jwt.expiration'),
        ];
    }
}

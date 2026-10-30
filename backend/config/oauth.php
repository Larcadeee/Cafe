<?php

return [
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI', 'http://localhost:3000/auth/callback'),
        'scopes' => ['openid', 'email', 'profile'],
        'endpoint' => 'https://oauth2.googleapis.com',
    ],

    'jwt' => [
        'secret' => env('JWT_SECRET', 'change-me-in-production'),
        'expiration' => (int) env('JWT_EXPIRATION', 900), // 15 minutes
        'refresh_expiration' => (int) env('JWT_REFRESH_EXPIRATION', 2592000), // 30 days
    ],

    'cors' => [
        'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')),
        'frontend_url' => env('FRONTEND_URL', 'http://localhost:3000'),
    ],
];

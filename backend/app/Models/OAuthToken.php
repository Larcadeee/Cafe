<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OAuthToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'refresh_token',
        'expires_at',
        'revoked',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'revoked' => 'boolean',
        ];
    }

    /**
     * Get the user that owns this refresh token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the refresh token is valid (not expired and not revoked).
     */
    public function isValid(): bool
    {
        return !$this->revoked && $this->expires_at->isFuture();
    }

    /**
     * Revoke this refresh token.
     */
    public function revoke(): void
    {
        $this->update(['revoked' => true]);
    }
}

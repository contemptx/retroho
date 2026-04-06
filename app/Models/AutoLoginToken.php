<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoLoginToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'token',
        'expires_at',
        'used_at',
        'ip_address',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    /**
     * Get the user that owns the token.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the token is still valid.
     */
    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    /**
     * Mark the token as used.
     */
    public function markAsUsed(?string $ipAddress = null): void
    {
        $this->used_at = now();
        $this->ip_address = $ipAddress;
        $this->save();
    }

    /**
     * Generate a new auto-login token for a user.
     * Token expires in 5 minutes by default.
     */
    public static function generateFor(User $user, int $expiresInMinutes = 5): self
    {
        // Delete any existing unused tokens for this user
        self::where('user_id', $user->id)
            ->whereNull('used_at')
            ->delete();

        return self::create([
            'user_id' => $user->id,
            'token' => bin2hex(random_bytes(32)),
            'expires_at' => now()->addMinutes($expiresInMinutes),
        ]);
    }
}

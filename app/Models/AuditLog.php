<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    protected $fillable = [
        'event_type',
        'entity_type',
        'entity_id',
        'user_id',
        'ip_address',
        'user_agent',
        'old_values',
        'new_values',
        'metadata',
        'severity',
        'occurred_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'metadata' => 'array',
        'occurred_at' => 'datetime',
    ];

    /**
     * Get the user that performed the action.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Log an audit event.
     */
    public static function logEvent(
        string $eventType,
        ?string $entityType = null,
        ?int $entityId = null,
        ?int $userId = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        string $severity = 'info'
    ): self {
        return self::create([
            'event_type' => $eventType,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'user_id' => $userId,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
            'severity' => $severity,
            'occurred_at' => now(),
        ]);
    }
}

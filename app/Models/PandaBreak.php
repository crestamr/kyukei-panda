<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PandaBreak extends Model
{
    protected $fillable = [
        'user_id',
        'slack_user_id',
        'slack_channel_id',
        'slack_message_ts',
        'channel_name',
        'panda_count',
        'break_duration',
        'break_timestamp',
        'is_valid',
        'message_text',
    ];

    protected $casts = [
        'break_timestamp' => 'datetime',
        'is_valid' => 'boolean',
    ];

    /**
     * Get the user that owns the panda break.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get breaks for a specific date.
     */
    public function scopeForDate($query, Carbon $date)
    {
        return $query->whereDate('break_timestamp', $date);
    }

    /**
     * Scope to get breaks between dates.
     */
    public function scopeBetweenDates($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('break_timestamp', [$startDate, $endDate]);
    }

    /**
     * Scope to get valid breaks.
     */
    public function scopeValid($query)
    {
        return $query->where('is_valid', true);
    }

    /**
     * Scope to get breaks for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get breaks for a specific Slack user.
     */
    public function scopeForSlackUser($query, string $slackUserId)
    {
        return $query->where('slack_user_id', $slackUserId);
    }

    /**
     * Get break duration in human readable format.
     */
    public function getDurationFormattedAttribute(): string
    {
        if ($this->break_duration >= 60) {
            $hours = floor($this->break_duration / 60);
            $minutes = $this->break_duration % 60;
            return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
        }

        return "{$this->break_duration}m";
    }

    /**
     * Get panda emojis representation.
     */
    public function getPandaEmojisAttribute(): string
    {
        return str_repeat('🐼', $this->panda_count);
    }
}

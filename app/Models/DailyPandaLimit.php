<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DailyPandaLimit extends Model
{
    protected $fillable = [
        'user_id',
        'date',
        'pandas_used',
        'total_break_minutes',
        'limit_exceeded_at',
        'first_break_at',
        'last_break_at',
    ];

    protected $casts = [
        'date' => 'date',
        'limit_exceeded_at' => 'datetime',
        'first_break_at' => 'datetime',
        'last_break_at' => 'datetime',
    ];

    /**
     * Get the user that owns the daily panda limit.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the panda breaks for this day.
     */
    public function pandaBreaks(): HasMany
    {
        return $this->hasMany(PandaBreak::class, 'user_id', 'user_id')
            ->whereDate('break_timestamp', $this->date);
    }

    /**
     * Check if user has exceeded daily limit.
     */
    public function hasExceededLimit(): bool
    {
        return $this->pandas_used >= 6;
    }

    /**
     * Get remaining pandas for the day.
     */
    public function getRemainingPandasAttribute(): int
    {
        return max(0, 6 - $this->pandas_used);
    }

    /**
     * Get remaining break minutes for the day.
     */
    public function getRemainingMinutesAttribute(): int
    {
        return max(0, 60 - $this->total_break_minutes);
    }

    /**
     * Get panda usage percentage.
     */
    public function getUsagePercentageAttribute(): float
    {
        return round(($this->pandas_used / 6) * 100, 1);
    }

    /**
     * Get visual representation of panda usage.
     */
    public function getPandaVisualizationAttribute(): string
    {
        $used = str_repeat('🐼', $this->pandas_used);
        $remaining = str_repeat('⚪', $this->remaining_pandas);
        return $used . $remaining;
    }

    /**
     * Scope to get limits for a specific date.
     */
    public function scopeForDate($query, Carbon $date)
    {
        return $query->where('date', $date->toDateString());
    }

    /**
     * Scope to get limits for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to get limits that have exceeded the daily limit.
     */
    public function scopeExceeded($query)
    {
        return $query->where('pandas_used', '>=', 6);
    }
}

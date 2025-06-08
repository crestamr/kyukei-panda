<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    protected $fillable = [
        'user_id',
        'project_id',
        'application_name',
        'window_title',
        'url',
        'category_id',
        'started_at',
        'ended_at',
        'duration_seconds',
        'productivity_score',
        'is_manual',
        'description',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'productivity_score' => 'decimal:2',
        'is_manual' => 'boolean',
    ];

    /**
     * Get the user that owns the activity.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the project associated with the activity.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the category associated with the activity.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Scope to get activities for a specific date.
     */
    public function scopeForDate($query, Carbon $date)
    {
        return $query->whereDate('started_at', $date);
    }

    /**
     * Scope to get activities between dates.
     */
    public function scopeBetweenDates($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('started_at', [$startDate, $endDate]);
    }

    /**
     * Scope to get productive activities.
     */
    public function scopeProductive($query)
    {
        return $query->whereHas('category', function ($q) {
            $q->where('is_productive', true);
        });
    }

    /**
     * Scope to get activities for a specific user.
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get duration in human readable format.
     */
    public function getDurationFormattedAttribute(): string
    {
        $hours = floor($this->duration_seconds / 3600);
        $minutes = floor(($this->duration_seconds % 3600) / 60);
        $seconds = $this->duration_seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm %ds', $hours, $minutes, $seconds);
        } elseif ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $seconds);
        } else {
            return sprintf('%ds', $seconds);
        }
    }
}

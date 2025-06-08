<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'name',
        'description',
        'color',
        'team_id',
        'client_id',
        'hourly_rate',
        'start_date',
        'end_date',
        'is_active',
    ];

    protected $casts = [
        'hourly_rate' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
    ];

    /**
     * Get the team that owns the project.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the client that owns the project.
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * Get the project's activities.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Get total time spent on this project.
     */
    public function getTotalTimeAttribute(): int
    {
        return $this->activities()->sum('duration_seconds');
    }

    /**
     * Get total billable amount for this project.
     */
    public function getTotalBillableAttribute(): float
    {
        if (!$this->hourly_rate) {
            return 0.0;
        }

        $totalHours = $this->getTotalTimeAttribute() / 3600;
        return $totalHours * $this->hourly_rate;
    }

    /**
     * Scope to get active projects.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get projects for a specific team.
     */
    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }
}

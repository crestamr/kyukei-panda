<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $fillable = [
        'name',
        'color',
        'productivity_score',
        'is_productive',
        'description',
        'keywords',
        'team_id',
        'is_global',
    ];

    protected $casts = [
        'productivity_score' => 'decimal:2',
        'is_productive' => 'boolean',
        'keywords' => 'array',
        'is_global' => 'boolean',
    ];

    /**
     * Get the team that owns the category.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the category's activities.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Scope to get productive categories.
     */
    public function scopeProductive($query)
    {
        return $query->where('is_productive', true);
    }

    /**
     * Scope to get global categories.
     */
    public function scopeGlobal($query)
    {
        return $query->where('is_global', true);
    }

    /**
     * Scope to get categories for a specific team.
     */
    public function scopeForTeam($query, int $teamId)
    {
        return $query->where(function ($q) use ($teamId) {
            $q->where('team_id', $teamId)->orWhere('is_global', true);
        });
    }

    /**
     * Check if this category matches given keywords.
     */
    public function matchesKeywords(string $text): bool
    {
        if (!$this->keywords) {
            return false;
        }

        $text = strtolower($text);
        foreach ($this->keywords as $keyword) {
            if (str_contains($text, strtolower($keyword))) {
                return true;
            }
        }

        return false;
    }
}

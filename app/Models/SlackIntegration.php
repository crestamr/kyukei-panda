<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlackIntegration extends Model
{
    protected $fillable = [
        'team_id',
        'slack_team_id',
        'slack_channel_id',
        'channel_name',
        'is_panda_enabled',
        'is_active',
        'settings',
        'bot_token',
        'last_sync_at',
    ];

    protected $casts = [
        'is_panda_enabled' => 'boolean',
        'is_active' => 'boolean',
        'settings' => 'array',
        'last_sync_at' => 'datetime',
    ];

    protected $hidden = [
        'bot_token',
    ];

    /**
     * Get the team that owns the Slack integration.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Scope to get active integrations.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get integrations with panda enabled.
     */
    public function scopePandaEnabled($query)
    {
        return $query->where('is_panda_enabled', true);
    }

    /**
     * Scope to get integrations for a specific team.
     */
    public function scopeForTeam($query, int $teamId)
    {
        return $query->where('team_id', $teamId);
    }

    /**
     * Scope to get integrations for a specific Slack team.
     */
    public function scopeForSlackTeam($query, string $slackTeamId)
    {
        return $query->where('slack_team_id', $slackTeamId);
    }

    /**
     * Check if this integration should process panda messages.
     */
    public function shouldProcessPandaMessages(): bool
    {
        return $this->is_active && $this->is_panda_enabled;
    }

    /**
     * Get setting value with default.
     */
    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Set setting value.
     */
    public function setSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->settings = $settings;
    }
}

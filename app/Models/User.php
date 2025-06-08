<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'slack_user_id',
        'slack_username',
        'timezone',
        'avatar_url',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the teams that the user belongs to.
     */
    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class)
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Get the user's activities.
     */
    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    /**
     * Get the user's panda breaks.
     */
    public function pandaBreaks(): HasMany
    {
        return $this->hasMany(PandaBreak::class);
    }

    /**
     * Get the user's daily panda limits.
     */
    public function dailyPandaLimits(): HasMany
    {
        return $this->hasMany(DailyPandaLimit::class);
    }

    /**
     * Check if user is admin of any team.
     */
    public function isTeamAdmin(): bool
    {
        return $this->teams()->wherePivot('role', 'admin')->exists();
    }

    /**
     * Check if user is admin of specific team.
     */
    public function isAdminOf(Team $team): bool
    {
        return $this->teams()->wherePivot('team_id', $team->id)->wherePivot('role', 'admin')->exists();
    }

    /**
     * Check if user is manager of specific team.
     */
    public function isManagerOf(Team $team): bool
    {
        return $this->teams()->wherePivot('team_id', $team->id)->wherePivot('role', 'manager')->exists();
    }
}

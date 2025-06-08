<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Activity;
use App\Models\AuditLog;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ComplianceService
{
    /**
     * GDPR compliance features.
     */
    public function handleGDPRRequest(int $userId, string $requestType): array
    {
        $user = User::find($userId);
        if (!$user) {
            return ['success' => false, 'error' => 'User not found'];
        }

        switch ($requestType) {
            case 'data_export':
                return $this->exportUserData($user);
            case 'data_deletion':
                return $this->deleteUserData($user);
            case 'data_portability':
                return $this->generatePortableData($user);
            case 'consent_withdrawal':
                return $this->withdrawConsent($user);
            default:
                return ['success' => false, 'error' => 'Invalid request type'];
        }
    }

    /**
     * Export all user data for GDPR compliance.
     */
    private function exportUserData(User $user): array
    {
        try {
            $userData = [
                'personal_information' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'locale' => $user->locale,
                    'timezone' => $user->timezone,
                    'created_at' => $user->created_at->toISOString(),
                    'updated_at' => $user->updated_at->toISOString(),
                ],
                'activities' => Activity::where('user_id', $user->id)
                    ->with('category', 'project')
                    ->get()
                    ->map(function ($activity) {
                        return [
                            'id' => $activity->id,
                            'application_name' => $activity->application_name,
                            'window_title' => $activity->window_title,
                            'url' => $activity->url,
                            'category' => $activity->category?->name,
                            'project' => $activity->project?->name,
                            'started_at' => $activity->started_at->toISOString(),
                            'ended_at' => $activity->ended_at?->toISOString(),
                            'duration_seconds' => $activity->duration_seconds,
                            'productivity_score' => $activity->productivity_score,
                        ];
                    }),
                'panda_breaks' => $user->pandaBreaks->map(function ($break) {
                    return [
                        'id' => $break->id,
                        'break_timestamp' => $break->break_timestamp->toISOString(),
                        'break_duration' => $break->break_duration,
                        'panda_count' => $break->panda_count,
                        'channel_name' => $break->channel_name,
                    ];
                }),
                'teams' => $user->teams->map(function ($team) {
                    return [
                        'id' => $team->id,
                        'name' => $team->name,
                        'role' => $team->pivot->role,
                        'joined_at' => $team->pivot->created_at,
                    ];
                }),
                'audit_logs' => AuditLog::where('user_id', $user->id)
                    ->get()
                    ->map(function ($log) {
                        return [
                            'event_type' => $log->event_type,
                            'occurred_at' => $log->occurred_at->toISOString(),
                            'ip_address' => $log->ip_address,
                            'user_agent' => $log->user_agent,
                        ];
                    }),
            ];

            // Store export file
            $filename = "user_data_export_{$user->id}_" . now()->format('Y-m-d_H-i-s') . '.json';
            $filePath = "gdpr_exports/{$filename}";
            
            Storage::disk('private')->put($filePath, json_encode($userData, JSON_PRETTY_PRINT));

            // Log the export request
            AuditLog::logEvent(
                'gdpr_data_export',
                'User',
                $user->id,
                $user->id,
                null,
                ['export_file' => $filename],
                ['request_type' => 'data_export'],
                'info'
            );

            return [
                'success' => true,
                'export_file' => $filename,
                'download_url' => route('gdpr.download', ['file' => $filename]),
                'expires_at' => now()->addDays(30)->toISOString(),
            ];

        } catch (\Exception $e) {
            Log::error('GDPR data export failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Export failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Delete user data for GDPR compliance.
     */
    private function deleteUserData(User $user): array
    {
        try {
            // Anonymize instead of hard delete to maintain data integrity
            $anonymizedData = [
                'name' => 'Deleted User ' . $user->id,
                'email' => 'deleted_' . $user->id . '@anonymized.local',
                'password' => Hash::make('deleted'),
                'locale' => null,
                'timezone' => null,
                'deleted_at' => now(),
            ];

            // Anonymize activities
            Activity::where('user_id', $user->id)->update([
                'window_title' => '[ANONYMIZED]',
                'url' => '[ANONYMIZED]',
                'description' => '[ANONYMIZED]',
            ]);

            // Delete panda breaks
            $user->pandaBreaks()->delete();

            // Remove from teams
            $user->teams()->detach();

            // Update user record
            $user->update($anonymizedData);

            // Log the deletion
            AuditLog::logEvent(
                'gdpr_data_deletion',
                'User',
                $user->id,
                $user->id,
                null,
                ['anonymized' => true],
                ['request_type' => 'data_deletion'],
                'warning'
            );

            return [
                'success' => true,
                'message' => 'User data has been anonymized successfully',
                'anonymized_at' => now()->toISOString(),
            ];

        } catch (\Exception $e) {
            Log::error('GDPR data deletion failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Deletion failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Generate data retention report.
     */
    public function generateDataRetentionReport(): array
    {
        $report = [
            'generated_at' => now()->toISOString(),
            'retention_policies' => [
                'activities' => '2 years',
                'panda_breaks' => '2 years',
                'audit_logs' => '7 years',
                'user_data' => 'Until account deletion',
            ],
            'data_summary' => [
                'total_users' => User::count(),
                'active_users_30_days' => User::where('updated_at', '>=', Carbon::now()->subDays(30))->count(),
                'total_activities' => Activity::count(),
                'activities_last_year' => Activity::where('started_at', '>=', Carbon::now()->subYear())->count(),
                'total_audit_logs' => AuditLog::count(),
                'audit_logs_last_year' => AuditLog::where('occurred_at', '>=', Carbon::now()->subYear())->count(),
            ],
            'cleanup_recommendations' => $this->getCleanupRecommendations(),
        ];

        return $report;
    }

    /**
     * Implement data encryption for sensitive fields.
     */
    public function encryptSensitiveData(array $data): array
    {
        $sensitiveFields = ['window_title', 'url', 'description'];
        $encrypted = $data;

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field]) && $data[$field] !== null) {
                $encrypted[$field] = Crypt::encryptString($data[$field]);
            }
        }

        return $encrypted;
    }

    /**
     * Decrypt sensitive data for authorized access.
     */
    public function decryptSensitiveData(array $data): array
    {
        $sensitiveFields = ['window_title', 'url', 'description'];
        $decrypted = $data;

        foreach ($sensitiveFields as $field) {
            if (isset($data[$field]) && $data[$field] !== null) {
                try {
                    $decrypted[$field] = Crypt::decryptString($data[$field]);
                } catch (\Exception $e) {
                    // Field might not be encrypted or corrupted
                    $decrypted[$field] = '[DECRYPTION_FAILED]';
                }
            }
        }

        return $decrypted;
    }

    /**
     * Generate compliance audit report.
     */
    public function generateComplianceAuditReport(Carbon $startDate, Carbon $endDate): array
    {
        $auditLogs = AuditLog::whereBetween('occurred_at', [$startDate, $endDate])
            ->with('user')
            ->get();

        $report = [
            'period' => [
                'start_date' => $startDate->toISOString(),
                'end_date' => $endDate->toISOString(),
            ],
            'summary' => [
                'total_events' => $auditLogs->count(),
                'unique_users' => $auditLogs->pluck('user_id')->unique()->count(),
                'event_types' => $auditLogs->groupBy('event_type')->map->count(),
                'severity_breakdown' => $auditLogs->groupBy('severity')->map->count(),
            ],
            'security_events' => $auditLogs->where('severity', 'critical')->map(function ($log) {
                return [
                    'event_type' => $log->event_type,
                    'user' => $log->user?->name ?? 'System',
                    'ip_address' => $log->ip_address,
                    'occurred_at' => $log->occurred_at->toISOString(),
                    'metadata' => $log->metadata,
                ];
            }),
            'data_access_events' => $auditLogs->whereIn('event_type', [
                'data_export', 'data_view', 'report_generated'
            ])->map(function ($log) {
                return [
                    'event_type' => $log->event_type,
                    'user' => $log->user?->name ?? 'System',
                    'entity_type' => $log->entity_type,
                    'entity_id' => $log->entity_id,
                    'occurred_at' => $log->occurred_at->toISOString(),
                ];
            }),
            'compliance_score' => $this->calculateComplianceScore($auditLogs),
        ];

        return $report;
    }

    /**
     * Implement role-based access control validation.
     */
    public function validateRBACAccess(User $user, string $resource, string $action): bool
    {
        // Get user's roles across all teams
        $userRoles = $user->teams()->get()->pluck('pivot.role')->unique();

        $permissions = [
            'admin' => [
                'users' => ['create', 'read', 'update', 'delete'],
                'teams' => ['create', 'read', 'update', 'delete'],
                'projects' => ['create', 'read', 'update', 'delete'],
                'reports' => ['create', 'read', 'update', 'delete'],
                'audit_logs' => ['read'],
                'compliance' => ['read', 'export'],
            ],
            'manager' => [
                'users' => ['read', 'update'],
                'teams' => ['read', 'update'],
                'projects' => ['create', 'read', 'update'],
                'reports' => ['create', 'read'],
                'audit_logs' => ['read'],
            ],
            'member' => [
                'users' => ['read'],
                'teams' => ['read'],
                'projects' => ['read'],
                'reports' => ['read'],
            ],
        ];

        foreach ($userRoles as $role) {
            if (isset($permissions[$role][$resource]) && 
                in_array($action, $permissions[$role][$resource])) {
                return true;
            }
        }

        // Log unauthorized access attempt
        AuditLog::logEvent(
            'unauthorized_access_attempt',
            $resource,
            null,
            $user->id,
            null,
            ['action' => $action, 'resource' => $resource],
            ['user_roles' => $userRoles->toArray()],
            'warning'
        );

        return false;
    }

    /**
     * Generate privacy impact assessment.
     */
    public function generatePrivacyImpactAssessment(): array
    {
        return [
            'assessment_date' => now()->toISOString(),
            'data_categories' => [
                'personal_identifiers' => [
                    'fields' => ['name', 'email'],
                    'purpose' => 'User identification and communication',
                    'legal_basis' => 'Legitimate interest',
                    'retention_period' => 'Until account deletion',
                ],
                'activity_data' => [
                    'fields' => ['application_name', 'window_title', 'url'],
                    'purpose' => 'Productivity tracking and insights',
                    'legal_basis' => 'Consent',
                    'retention_period' => '2 years',
                ],
                'behavioral_data' => [
                    'fields' => ['productivity_score', 'break_patterns'],
                    'purpose' => 'AI insights and recommendations',
                    'legal_basis' => 'Consent',
                    'retention_period' => '2 years',
                ],
            ],
            'risk_assessment' => [
                'data_breach_risk' => 'Medium',
                'privacy_risk' => 'Low',
                'mitigation_measures' => [
                    'Data encryption at rest and in transit',
                    'Role-based access control',
                    'Regular security audits',
                    'Data anonymization options',
                    'User consent management',
                ],
            ],
            'compliance_status' => [
                'gdpr' => 'Compliant',
                'ccpa' => 'Compliant',
                'hipaa' => 'Not applicable',
                'sox' => 'Partially compliant',
            ],
        ];
    }

    /**
     * Get cleanup recommendations for old data.
     */
    private function getCleanupRecommendations(): array
    {
        $recommendations = [];

        // Check for old activities
        $oldActivitiesCount = Activity::where('started_at', '<', Carbon::now()->subYears(2))->count();
        if ($oldActivitiesCount > 0) {
            $recommendations[] = [
                'type' => 'data_cleanup',
                'description' => "Consider archiving {$oldActivitiesCount} activities older than 2 years",
                'priority' => 'medium',
                'estimated_space_saved' => $oldActivitiesCount * 0.5 . ' KB',
            ];
        }

        // Check for old audit logs
        $oldAuditLogsCount = AuditLog::where('occurred_at', '<', Carbon::now()->subYears(7))->count();
        if ($oldAuditLogsCount > 0) {
            $recommendations[] = [
                'type' => 'audit_cleanup',
                'description' => "Archive {$oldAuditLogsCount} audit logs older than 7 years",
                'priority' => 'low',
                'estimated_space_saved' => $oldAuditLogsCount * 1 . ' KB',
            ];
        }

        return $recommendations;
    }

    /**
     * Calculate compliance score based on audit logs.
     */
    private function calculateComplianceScore($auditLogs): array
    {
        $totalEvents = $auditLogs->count();
        $securityEvents = $auditLogs->where('severity', 'critical')->count();
        $unauthorizedAttempts = $auditLogs->where('event_type', 'unauthorized_access_attempt')->count();

        $score = 100;
        
        if ($totalEvents > 0) {
            $score -= ($securityEvents / $totalEvents) * 30;
            $score -= ($unauthorizedAttempts / $totalEvents) * 20;
        }

        $score = max(0, min(100, $score));

        return [
            'score' => round($score, 1),
            'grade' => $score >= 90 ? 'A' : ($score >= 80 ? 'B' : ($score >= 70 ? 'C' : 'D')),
            'factors' => [
                'security_incidents' => $securityEvents,
                'unauthorized_attempts' => $unauthorizedAttempts,
                'total_events' => $totalEvents,
            ],
        ];
    }
}

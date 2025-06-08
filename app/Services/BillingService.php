<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Client;
use App\Models\Project;
use App\Models\Activity;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class BillingService
{
    /**
     * Generate invoice data for a client and period.
     */
    public function generateInvoice(Client $client, Carbon $startDate, Carbon $endDate, array $options = []): array
    {
        $projects = $client->projects()
            ->where('is_active', true)
            ->whereNotNull('hourly_rate')
            ->with(['activities' => function ($query) use ($startDate, $endDate) {
                $query->whereBetween('started_at', [$startDate, $endDate])
                      ->with(['user', 'category']);
            }])
            ->get();

        $invoiceItems = [];
        $totalAmount = 0;
        $totalHours = 0;

        foreach ($projects as $project) {
            $projectActivities = $project->activities;
            
            if ($projectActivities->isEmpty()) {
                continue;
            }

            $projectTime = $projectActivities->sum('duration_seconds');
            $projectHours = $projectTime / 3600;
            $projectAmount = $projectHours * $project->hourly_rate;

            $totalHours += $projectHours;
            $totalAmount += $projectAmount;

            // Group activities by user for detailed breakdown
            $userBreakdown = $projectActivities->groupBy('user_id')->map(function ($userActivities, $userId) use ($project) {
                $user = $userActivities->first()->user;
                $userTime = $userActivities->sum('duration_seconds');
                $userHours = $userTime / 3600;
                $userAmount = $userHours * $project->hourly_rate;

                return [
                    'user_name' => $user->name,
                    'hours' => round($userHours, 2),
                    'rate' => $project->hourly_rate,
                    'amount' => round($userAmount, 2),
                    'activities_count' => $userActivities->count(),
                ];
            });

            $invoiceItems[] = [
                'project_id' => $project->id,
                'project_name' => $project->name,
                'project_description' => $project->description,
                'hourly_rate' => $project->hourly_rate,
                'total_hours' => round($projectHours, 2),
                'total_amount' => round($projectAmount, 2),
                'activities_count' => $projectActivities->count(),
                'user_breakdown' => $userBreakdown->values(),
                'period_start' => $startDate->toDateString(),
                'period_end' => $endDate->toDateString(),
            ];
        }

        // Calculate taxes if specified
        $taxRate = $options['tax_rate'] ?? 0;
        $taxAmount = $totalAmount * ($taxRate / 100);
        $grandTotal = $totalAmount + $taxAmount;

        return [
            'invoice_number' => $this->generateInvoiceNumber($client, $startDate),
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'email' => $client->email,
                'phone' => $client->phone,
                'address' => $client->address,
            ],
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'description' => $startDate->format('M Y'),
            ],
            'items' => $invoiceItems,
            'summary' => [
                'total_hours' => round($totalHours, 2),
                'subtotal' => round($totalAmount, 2),
                'tax_rate' => $taxRate,
                'tax_amount' => round($taxAmount, 2),
                'total_amount' => round($grandTotal, 2),
                'projects_count' => count($invoiceItems),
            ],
            'generated_at' => now()->toISOString(),
            'generated_by' => auth()->user()?->name ?? 'System',
        ];
    }

    /**
     * Generate a unique invoice number.
     */
    private function generateInvoiceNumber(Client $client, Carbon $date): string
    {
        $clientCode = strtoupper(substr($client->name, 0, 3));
        $dateCode = $date->format('Ym');
        $sequence = str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);
        
        return "INV-{$clientCode}-{$dateCode}-{$sequence}";
    }

    /**
     * Generate time tracking summary for a team.
     */
    public function generateTeamSummary(int $teamId, Carbon $startDate, Carbon $endDate): array
    {
        $activities = Activity::whereHas('user.teams', function ($query) use ($teamId) {
                $query->where('teams.id', $teamId);
            })
            ->whereBetween('started_at', [$startDate, $endDate])
            ->with(['user', 'project.client', 'category'])
            ->get();

        // Team member breakdown
        $memberStats = $activities->groupBy('user_id')->map(function ($userActivities, $userId) {
            $user = $userActivities->first()->user;
            $totalTime = $userActivities->sum('duration_seconds');
            $productiveTime = $userActivities->where('productivity_score', '>=', 0.5)->sum('duration_seconds');
            $billableTime = $userActivities->whereNotNull('project.hourly_rate')->sum('duration_seconds');
            
            $billableAmount = $userActivities->sum(function ($activity) {
                if ($activity->project && $activity->project->hourly_rate) {
                    return ($activity->duration_seconds / 3600) * $activity->project->hourly_rate;
                }
                return 0;
            });

            return [
                'user_id' => $userId,
                'user_name' => $user->name,
                'total_time' => $totalTime,
                'total_time_formatted' => $this->formatDuration($totalTime),
                'productive_time' => $productiveTime,
                'billable_time' => $billableTime,
                'billable_amount' => round($billableAmount, 2),
                'productivity_score' => $totalTime > 0 ? round(($productiveTime / $totalTime) * 100, 1) : 0,
                'activities_count' => $userActivities->count(),
                'projects_worked' => $userActivities->whereNotNull('project_id')->pluck('project_id')->unique()->count(),
            ];
        })->sortByDesc('total_time')->values();

        // Project breakdown
        $projectStats = $activities->whereNotNull('project_id')->groupBy('project_id')->map(function ($projectActivities, $projectId) {
            $project = $projectActivities->first()->project;
            $totalTime = $projectActivities->sum('duration_seconds');
            $billableAmount = $project && $project->hourly_rate ? 
                ($totalTime / 3600) * $project->hourly_rate : 0;

            return [
                'project_id' => $projectId,
                'project_name' => $project?->name,
                'client_name' => $project?->client?->name,
                'total_time' => $totalTime,
                'total_time_formatted' => $this->formatDuration($totalTime),
                'billable_amount' => round($billableAmount, 2),
                'activities_count' => $projectActivities->count(),
                'team_members_count' => $projectActivities->pluck('user_id')->unique()->count(),
            ];
        })->sortByDesc('total_time')->values();

        // Client breakdown
        $clientStats = $activities->whereNotNull('project.client_id')->groupBy('project.client_id')->map(function ($clientActivities) {
            $client = $clientActivities->first()->project->client;
            $totalTime = $clientActivities->sum('duration_seconds');
            
            $billableAmount = $clientActivities->sum(function ($activity) {
                if ($activity->project && $activity->project->hourly_rate) {
                    return ($activity->duration_seconds / 3600) * $activity->project->hourly_rate;
                }
                return 0;
            });

            return [
                'client_id' => $client->id,
                'client_name' => $client->name,
                'total_time' => $totalTime,
                'total_time_formatted' => $this->formatDuration($totalTime),
                'billable_amount' => round($billableAmount, 2),
                'projects_count' => $clientActivities->pluck('project_id')->unique()->count(),
                'activities_count' => $clientActivities->count(),
            ];
        })->sortByDesc('billable_amount')->values();

        $totalTime = $activities->sum('duration_seconds');
        $totalBillable = $memberStats->sum('billable_amount');
        $avgProductivity = $memberStats->avg('productivity_score');

        return [
            'summary' => [
                'total_time' => $totalTime,
                'total_time_formatted' => $this->formatDuration($totalTime),
                'total_billable' => round($totalBillable, 2),
                'avg_productivity' => round($avgProductivity, 1),
                'activities_count' => $activities->count(),
                'team_members_count' => $memberStats->count(),
                'projects_count' => $projectStats->count(),
                'clients_count' => $clientStats->count(),
            ],
            'member_stats' => $memberStats,
            'project_stats' => $projectStats,
            'client_stats' => $clientStats,
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
            ],
        ];
    }

    /**
     * Export invoice data to CSV format.
     */
    public function exportInvoiceToCSV(array $invoiceData): string
    {
        $csvData = [];
        
        // Header
        $csvData[] = [
            'Invoice Number', $invoiceData['invoice_number'],
            'Client', $invoiceData['client']['name'],
            'Period', $invoiceData['period']['description'],
        ];
        $csvData[] = []; // Empty row

        // Items header
        $csvData[] = [
            'Project', 'Description', 'Hours', 'Rate', 'Amount', 'Team Member', 'Member Hours', 'Member Amount'
        ];

        // Items data
        foreach ($invoiceData['items'] as $item) {
            $firstRow = true;
            foreach ($item['user_breakdown'] as $userBreakdown) {
                $csvData[] = [
                    $firstRow ? $item['project_name'] : '',
                    $firstRow ? $item['project_description'] : '',
                    $firstRow ? $item['total_hours'] : '',
                    $firstRow ? $item['hourly_rate'] : '',
                    $firstRow ? $item['total_amount'] : '',
                    $userBreakdown['user_name'],
                    $userBreakdown['hours'],
                    $userBreakdown['amount'],
                ];
                $firstRow = false;
            }
        }

        $csvData[] = []; // Empty row
        
        // Summary
        $csvData[] = ['Summary'];
        $csvData[] = ['Total Hours', $invoiceData['summary']['total_hours']];
        $csvData[] = ['Subtotal', $invoiceData['summary']['subtotal']];
        $csvData[] = ['Tax', $invoiceData['summary']['tax_amount']];
        $csvData[] = ['Total Amount', $invoiceData['summary']['total_amount']];

        // Convert to CSV string
        $output = fopen('php://temp', 'r+');
        foreach ($csvData as $row) {
            fputcsv($output, $row);
        }
        rewind($output);
        $csvString = stream_get_contents($output);
        fclose($output);

        return $csvString;
    }

    /**
     * Format duration in seconds to human readable format.
     */
    private function formatDuration(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        if ($hours > 0) {
            return "{$hours}h {$minutes}m";
        } else {
            return "{$minutes}m";
        }
    }
}

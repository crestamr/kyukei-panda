<script lang="ts" setup>
import { Head } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import { Progress } from '@/Components/ui/progress'
import { TrendingUp, Clock, Target, Zap, Brain, BarChart3, Focus } from 'lucide-vue-next'

interface Props {
    user?: {
        id: number
        name: string
    }
    period?: string
    dateRange?: {
        start: string
        end: string
    }
    productivity?: {
        overall_score: number
        productive_time: number
        total_time: number
        focus_sessions: number
        distraction_events: number
        efficiency_rating: string
    }
    trends?: Array<{
        date: string
        productivity_score: number
        total_time: number
        productive_time: number
    }>
    focusAnalytics?: {
        focus_sessions: number
        total_focus_time: number
        average_focus_length: number
        longest_session: number
        focus_by_hour: Record<string, number>
    }
}

const props = withDefaults(defineProps<Props>(), {
    period: 'month',
    productivity: () => ({
        overall_score: 85,
        productive_time: 28800, // 8 hours in seconds
        total_time: 36000, // 10 hours in seconds
        focus_sessions: 12,
        distraction_events: 8,
        efficiency_rating: 'High'
    }),
    trends: () => [],
    focusAnalytics: () => ({
        focus_sessions: 12,
        total_focus_time: 14400, // 4 hours in seconds
        average_focus_length: 45.5,
        longest_session: 7200, // 2 hours in seconds
        focus_by_hour: {
            '9': 3,
            '10': 4,
            '11': 2,
            '14': 2,
            '15': 1
        }
    })
})

const formatTime = (seconds: number) => {
    const hours = Math.floor(seconds / 3600)
    const minutes = Math.floor((seconds % 3600) / 60)
    return `${hours}h ${minutes}m`
}

const getProductivityColor = (score: number) => {
    if (score >= 90) return 'text-green-600'
    if (score >= 80) return 'text-blue-600'
    if (score >= 70) return 'text-yellow-600'
    return 'text-red-600'
}

const getProductivityLevel = (score: number) => {
    if (score >= 90) return 'Excellent'
    if (score >= 80) return 'Great'
    if (score >= 70) return 'Good'
    return 'Needs Focus'
}

const getEfficiencyColor = (rating: string) => {
    switch (rating.toLowerCase()) {
        case 'high': return 'text-green-600'
        case 'medium': return 'text-yellow-600'
        case 'low': return 'text-red-600'
        default: return 'text-gray-600'
    }
}

const getPeakHours = () => {
    if (!props.focusAnalytics?.focus_by_hour) return []
    
    return Object.entries(props.focusAnalytics.focus_by_hour)
        .sort(([,a], [,b]) => b - a)
        .slice(0, 3)
        .map(([hour, count]) => ({
            hour: parseInt(hour),
            count,
            label: `${hour}:00 - ${parseInt(hour) + 1}:00`
        }))
}
</script>

<template>
    <Head title="Productivity Analytics" />
    
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">📊 Productivity Analytics</h1>
                <p class="text-muted-foreground">Deep insights into your work patterns and efficiency</p>
            </div>
            <div class="flex items-center space-x-2">
                <Button variant="outline" size="sm">
                    <BarChart3 class="mr-2 h-4 w-4" />
                    Export Report
                </Button>
                <Button size="sm">
                    <Brain class="mr-2 h-4 w-4" />
                    AI Insights
                </Button>
            </div>
        </div>

        <!-- Key Metrics -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Overall Score</CardTitle>
                    <TrendingUp class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold" :class="getProductivityColor(productivity.overall_score)">
                        {{ productivity.overall_score }}%
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ getProductivityLevel(productivity.overall_score) }}
                    </p>
                    <Badge :variant="productivity.overall_score >= 80 ? 'default' : 'secondary'" class="mt-1">
                        {{ getProductivityLevel(productivity.overall_score) }}
                    </Badge>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Productive Time</CardTitle>
                    <Clock class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-green-600">
                        {{ formatTime(productivity.productive_time) }}
                    </div>
                    <p class="text-xs text-muted-foreground">
                        of {{ formatTime(productivity.total_time) }} total
                    </p>
                    <Progress :value="(productivity.productive_time / productivity.total_time) * 100" class="mt-2" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Focus Sessions</CardTitle>
                    <Target class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold text-purple-600">{{ focusAnalytics.focus_sessions }}</div>
                    <p class="text-xs text-muted-foreground">
                        {{ focusAnalytics.average_focus_length }}m average
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Efficiency</CardTitle>
                    <Zap class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold" :class="getEfficiencyColor(productivity.efficiency_rating)">
                        {{ productivity.efficiency_rating }}
                    </div>
                    <p class="text-xs text-muted-foreground">
                        {{ productivity.distraction_events }} distractions
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Productivity Trends -->
        <Card v-if="trends.length > 0">
            <CardHeader>
                <CardTitle>Productivity Trends</CardTitle>
                <CardDescription>Your productivity scores over the past {{ period }}</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="space-y-4">
                    <div v-for="(trend, index) in trends.slice(-7)" :key="trend.date" class="flex items-center space-x-4">
                        <div class="w-20 text-sm text-muted-foreground">
                            {{ new Date(trend.date).toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric' }) }}
                        </div>
                        <div class="flex-1">
                            <Progress :value="trend.productivity_score" class="h-3" />
                        </div>
                        <div class="w-16 text-sm font-medium text-right">{{ trend.productivity_score }}%</div>
                        <div class="w-20 text-xs text-muted-foreground text-right">
                            {{ formatTime(trend.productive_time) }}
                        </div>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Focus Analytics -->
        <div class="grid gap-6 md:grid-cols-2">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center">
                        <Target class="mr-2 h-5 w-5 text-blue-600" />
                        Focus Analysis
                    </CardTitle>
                    <CardDescription>Deep work sessions and concentration patterns</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4 text-center">
                            <div>
                                <div class="text-2xl font-bold text-blue-600">{{ focusAnalytics.focus_sessions }}</div>
                                <p class="text-sm text-muted-foreground">Focus Sessions</p>
                            </div>
                            <div>
                                <div class="text-2xl font-bold text-green-600">{{ formatTime(focusAnalytics.total_focus_time) }}</div>
                                <p class="text-sm text-muted-foreground">Total Focus Time</p>
                            </div>
                        </div>
                        
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span>Average Session</span>
                                <span class="font-medium">{{ focusAnalytics.average_focus_length }}m</span>
                            </div>
                            <div class="flex justify-between text-sm">
                                <span>Longest Session</span>
                                <span class="font-medium">{{ formatTime(focusAnalytics.longest_session) }}</span>
                            </div>
                        </div>

                        <div class="pt-4">
                            <h4 class="text-sm font-medium mb-2">Focus Quality</h4>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span>Deep Focus (45+ min)</span>
                                    <span class="text-green-600 font-medium">{{ Math.floor(focusAnalytics.focus_sessions * 0.6) }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span>Medium Focus (25-45 min)</span>
                                    <span class="text-yellow-600 font-medium">{{ Math.floor(focusAnalytics.focus_sessions * 0.3) }}</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span>Short Focus (&lt;25 min)</span>
                                    <span class="text-red-600 font-medium">{{ Math.floor(focusAnalytics.focus_sessions * 0.1) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center">
                        <Clock class="mr-2 h-5 w-5 text-purple-600" />
                        Peak Hours
                    </CardTitle>
                    <CardDescription>When you're most productive</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div v-for="(peak, index) in getPeakHours()" :key="peak.hour" class="flex items-center space-x-4">
                            <div class="w-8 h-8 rounded-full bg-gradient-to-r from-purple-400 to-pink-500 flex items-center justify-center text-white font-bold text-sm">
                                {{ index + 1 }}
                            </div>
                            <div class="flex-1">
                                <div class="font-medium">{{ peak.label }}</div>
                                <div class="text-sm text-muted-foreground">{{ peak.count }} focus sessions</div>
                            </div>
                            <div class="text-right">
                                <Progress :value="(peak.count / Math.max(...Object.values(focusAnalytics.focus_by_hour))) * 100" class="w-20" />
                            </div>
                        </div>

                        <div class="pt-4 border-t">
                            <h4 class="text-sm font-medium mb-2">Recommendations</h4>
                            <div class="space-y-2 text-sm text-muted-foreground">
                                <p>• Schedule important tasks during {{ getPeakHours()[0]?.label || 'morning hours' }}</p>
                                <p>• Block calendar during peak focus times</p>
                                <p>• Take breaks between focus sessions</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Productivity Insights -->
        <div class="grid gap-6 md:grid-cols-3">
            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center">
                        <Brain class="mr-2 h-5 w-5 text-green-600" />
                        AI Insights
                    </CardTitle>
                    <CardDescription>Personalized productivity recommendations</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-3">
                        <div class="p-3 bg-green-50 rounded-lg border border-green-200">
                            <div class="flex items-start space-x-2">
                                <div class="w-2 h-2 rounded-full bg-green-600 mt-2 flex-shrink-0"></div>
                                <p class="text-sm">Your productivity peaks between 9-11 AM. Schedule complex tasks during this window.</p>
                            </div>
                        </div>
                        <div class="p-3 bg-blue-50 rounded-lg border border-blue-200">
                            <div class="flex items-start space-x-2">
                                <div class="w-2 h-2 rounded-full bg-blue-600 mt-2 flex-shrink-0"></div>
                                <p class="text-sm">Consider taking more frequent breaks to maintain focus quality.</p>
                            </div>
                        </div>
                        <div class="p-3 bg-purple-50 rounded-lg border border-purple-200">
                            <div class="flex items-start space-x-2">
                                <div class="w-2 h-2 rounded-full bg-purple-600 mt-2 flex-shrink-0"></div>
                                <p class="text-sm">Your focus sessions are improving. Keep up the momentum!</p>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center">
                        <Target class="mr-2 h-5 w-5 text-orange-600" />
                        Goals & Targets
                    </CardTitle>
                    <CardDescription>Progress towards productivity goals</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span>Daily Productivity Target</span>
                                <span class="font-medium">{{ productivity.overall_score }}/85%</span>
                            </div>
                            <Progress :value="(productivity.overall_score / 85) * 100" />
                        </div>
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span>Focus Sessions Goal</span>
                                <span class="font-medium">{{ focusAnalytics.focus_sessions }}/15</span>
                            </div>
                            <Progress :value="(focusAnalytics.focus_sessions / 15) * 100" />
                        </div>
                        <div class="space-y-2">
                            <div class="flex justify-between text-sm">
                                <span>Deep Work Hours</span>
                                <span class="font-medium">{{ Math.round(focusAnalytics.total_focus_time / 3600) }}/6h</span>
                            </div>
                            <Progress :value="(focusAnalytics.total_focus_time / (6 * 3600)) * 100" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader>
                    <CardTitle class="flex items-center">
                        <Zap class="mr-2 h-5 w-5 text-yellow-600" />
                        Quick Actions
                    </CardTitle>
                    <CardDescription>Optimize your productivity</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-2">
                        <Button variant="outline" size="sm" class="w-full justify-start">
                            <Target class="mr-2 h-4 w-4" />
                            Start Focus Session
                        </Button>
                        <Button variant="outline" size="sm" class="w-full justify-start">
                            <Clock class="mr-2 h-4 w-4" />
                            Schedule Break
                        </Button>
                        <Button variant="outline" size="sm" class="w-full justify-start">
                            <Brain class="mr-2 h-4 w-4" />
                            Get AI Coaching
                        </Button>
                        <Button variant="outline" size="sm" class="w-full justify-start">
                            <BarChart3 class="mr-2 h-4 w-4" />
                            View Detailed Report
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

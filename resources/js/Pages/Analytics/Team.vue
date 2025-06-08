<script lang="ts" setup>
import { Head } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import { Progress } from '@/Components/ui/progress'
import { Avatar, AvatarFallback, AvatarImage } from '@/Components/ui/avatar'
import { Users, TrendingUp, Trophy, Clock, Target, Zap } from 'lucide-vue-next'

interface Props {
    teams?: Array<{
        id: number
        name: string
        member_count: number
    }>
    selectedTeam?: {
        id: number
        name: string
        description: string
    }
    period?: string
    dateRange?: {
        start: string
        end: string
    }
    teamComparison?: Array<{
        user_id: number
        name: string
        productivity_score: number
        total_pandas: number
        productive_time: number
        total_time: number
    }>
    teamPandaStats?: {
        total_pandas: number
        total_breaks: number
        total_break_time: number
        average_pandas_per_member: number
        member_stats: Array<{
            user_id: number
            name: string
            pandas: number
            breaks: number
            break_time: number
        }>
        team_size: number
    }
    error?: string
}

const props = withDefaults(defineProps<Props>(), {
    teams: () => [],
    period: 'week',
    teamComparison: () => [],
    error: undefined
})

const getInitials = (name: string) => {
    return name.split(' ').map(n => n[0]).join('').toUpperCase()
}

const getProductivityColor = (score: number) => {
    if (score >= 90) return 'text-green-600'
    if (score >= 80) return 'text-blue-600'
    if (score >= 70) return 'text-yellow-600'
    return 'text-red-600'
}

const formatTime = (seconds: number) => {
    const hours = Math.floor(seconds / 3600)
    const minutes = Math.floor((seconds % 3600) / 60)
    return `${hours}h ${minutes}m`
}
</script>

<template>
    <Head title="Team Analytics" />
    
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">👥 Team Analytics</h1>
                <p class="text-muted-foreground">Analyze your team's productivity and collaboration patterns</p>
            </div>
            <div class="flex items-center space-x-2">
                <Button variant="outline" size="sm">
                    <TrendingUp class="mr-2 h-4 w-4" />
                    Export Report
                </Button>
                <Button size="sm">
                    <Users class="mr-2 h-4 w-4" />
                    Manage Team
                </Button>
            </div>
        </div>

        <!-- Error State -->
        <div v-if="error" class="text-center py-12">
            <Card>
                <CardContent class="pt-6">
                    <div class="text-muted-foreground mb-4">
                        <Users class="mx-auto h-12 w-12 mb-4" />
                        <p>{{ error }}</p>
                    </div>
                    <Button>Join a Team</Button>
                </CardContent>
            </Card>
        </div>

        <!-- Team Analytics Content -->
        <div v-else class="space-y-6">
            <!-- Team Overview Cards -->
            <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4" v-if="teamPandaStats">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Team Pandas</CardTitle>
                        <span class="text-2xl">🐼</span>
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-green-600">{{ teamPandaStats.total_pandas }}</div>
                        <p class="text-xs text-muted-foreground">
                            {{ teamPandaStats.average_pandas_per_member }} avg per member
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Breaks</CardTitle>
                        <Clock class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-blue-600">{{ teamPandaStats.total_breaks }}</div>
                        <p class="text-xs text-muted-foreground">
                            {{ Math.round(teamPandaStats.total_breaks / teamPandaStats.team_size) }} per member
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Break Time</CardTitle>
                        <Target class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-purple-600">{{ teamPandaStats.total_break_time }}m</div>
                        <p class="text-xs text-muted-foreground">
                            {{ Math.round(teamPandaStats.total_break_time / teamPandaStats.team_size) }}m per member
                        </p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Team Size</CardTitle>
                        <Users class="h-4 w-4 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-2xl font-bold text-orange-600">{{ teamPandaStats.team_size }}</div>
                        <p class="text-xs text-muted-foreground">
                            Active members
                        </p>
                    </CardContent>
                </Card>
            </div>

            <!-- Team Leaderboard -->
            <div class="grid gap-6 md:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center">
                            <Trophy class="mr-2 h-5 w-5 text-yellow-600" />
                            Productivity Leaderboard
                        </CardTitle>
                        <CardDescription>Top performers this {{ period }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div v-for="(member, index) in teamComparison.slice(0, 5)" :key="member.user_id" class="flex items-center space-x-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-r from-yellow-400 to-orange-500 flex items-center justify-center text-white font-bold text-sm">
                                        {{ index + 1 }}
                                    </div>
                                    <Avatar class="h-8 w-8">
                                        <AvatarFallback>{{ getInitials(member.name) }}</AvatarFallback>
                                    </Avatar>
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium">{{ member.name }}</div>
                                    <div class="text-sm text-muted-foreground">
                                        {{ member.total_pandas }} pandas • {{ formatTime(member.productive_time) }}
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="font-bold" :class="getProductivityColor(member.productivity_score)">
                                        {{ member.productivity_score }}%
                                    </div>
                                    <Badge variant="secondary" class="text-xs">
                                        {{ member.productivity_score >= 90 ? 'Excellent' : member.productivity_score >= 80 ? 'Great' : 'Good' }}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Panda Champions -->
                <Card v-if="teamPandaStats">
                    <CardHeader>
                        <CardTitle class="flex items-center">
                            <span class="mr-2 text-xl">🐼</span>
                            Panda Champions
                        </CardTitle>
                        <CardDescription>Most pandas earned this {{ period }}</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div v-for="(member, index) in teamPandaStats.member_stats.sort((a, b) => b.pandas - a.pandas).slice(0, 5)" :key="member.user_id" class="flex items-center space-x-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-8 h-8 rounded-full bg-gradient-to-r from-green-400 to-blue-500 flex items-center justify-center text-white font-bold text-sm">
                                        {{ index + 1 }}
                                    </div>
                                    <Avatar class="h-8 w-8">
                                        <AvatarFallback>{{ getInitials(member.name) }}</AvatarFallback>
                                    </Avatar>
                                </div>
                                <div class="flex-1">
                                    <div class="font-medium">{{ member.name }}</div>
                                    <div class="text-sm text-muted-foreground">
                                        {{ member.breaks }} breaks • {{ member.break_time }}m total
                                    </div>
                                </div>
                                <div class="text-right">
                                    <div class="text-2xl font-bold text-green-600">{{ member.pandas }}</div>
                                    <div class="text-xs text-muted-foreground">pandas</div>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Team Insights -->
            <div class="grid gap-6 md:grid-cols-3">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center">
                            <TrendingUp class="mr-2 h-5 w-5 text-blue-600" />
                            Team Trends
                        </CardTitle>
                        <CardDescription>Performance patterns and insights</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span>Average Productivity</span>
                                    <span class="font-medium">{{ teamComparison.length > 0 ? Math.round(teamComparison.reduce((sum, m) => sum + m.productivity_score, 0) / teamComparison.length) : 0 }}%</span>
                                </div>
                                <Progress :value="teamComparison.length > 0 ? teamComparison.reduce((sum, m) => sum + m.productivity_score, 0) / teamComparison.length : 0" />
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span>Break Compliance</span>
                                    <span class="font-medium">{{ teamPandaStats ? Math.round((teamPandaStats.member_stats.filter(m => m.pandas >= 3).length / teamPandaStats.team_size) * 100) : 0 }}%</span>
                                </div>
                                <Progress :value="teamPandaStats ? (teamPandaStats.member_stats.filter(m => m.pandas >= 3).length / teamPandaStats.team_size) * 100 : 0" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center">
                            <Zap class="mr-2 h-5 w-5 text-purple-600" />
                            Team Health
                        </CardTitle>
                        <CardDescription>Wellness and balance metrics</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div class="text-center">
                                <div class="text-3xl font-bold text-green-600">Healthy</div>
                                <p class="text-sm text-muted-foreground">Team wellness status</p>
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span>Work-Life Balance</span>
                                    <span class="text-green-600 font-medium">Good</span>
                                </div>
                                <div class="flex justify-between text-sm">
                                    <span>Burnout Risk</span>
                                    <span class="text-green-600 font-medium">Low</span>
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center">
                            <Target class="mr-2 h-5 w-5 text-orange-600" />
                            Team Goals
                        </CardTitle>
                        <CardDescription>Progress towards team objectives</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-4">
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span>Weekly Panda Goal</span>
                                    <span class="font-medium">{{ teamPandaStats ? teamPandaStats.total_pandas : 0 }}/{{ teamPandaStats ? teamPandaStats.team_size * 21 : 0 }}</span>
                                </div>
                                <Progress :value="teamPandaStats ? (teamPandaStats.total_pandas / (teamPandaStats.team_size * 21)) * 100 : 0" />
                            </div>
                            <div class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span>Team Productivity Target</span>
                                    <span class="font-medium">{{ teamComparison.length > 0 ? Math.round(teamComparison.reduce((sum, m) => sum + m.productivity_score, 0) / teamComparison.length) : 0 }}/85%</span>
                                </div>
                                <Progress :value="teamComparison.length > 0 ? (teamComparison.reduce((sum, m) => sum + m.productivity_score, 0) / teamComparison.length) / 85 * 100 : 0" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Quick Actions -->
            <Card>
                <CardHeader>
                    <CardTitle>Team Management</CardTitle>
                    <CardDescription>Manage your team and boost collaboration</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="flex flex-wrap gap-2">
                        <Button variant="outline" size="sm">
                            <Users class="mr-2 h-4 w-4" />
                            Schedule Team Break
                        </Button>
                        <Button variant="outline" size="sm">
                            <TrendingUp class="mr-2 h-4 w-4" />
                            Set Team Goals
                        </Button>
                        <Button variant="outline" size="sm">
                            <Trophy class="mr-2 h-4 w-4" />
                            Create Challenge
                        </Button>
                        <Button variant="outline" size="sm">
                            <span class="mr-2">🎯</span>
                            Team Retrospective
                        </Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </div>
</template>

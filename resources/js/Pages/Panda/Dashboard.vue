<script lang="ts" setup>
import { Head } from '@inertiajs/vue3'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/Components/ui/card'
import { Button } from '@/Components/ui/button'
import { Badge } from '@/Components/ui/badge'
import { Progress } from '@/Components/ui/progress'
import { Separator } from '@/Components/ui/separator'
import { Clock, Coffee, TrendingUp, Users, Zap, Trophy } from 'lucide-vue-next'
import { ref, computed } from 'vue'

interface Props {
    dailyUsage?: {
        pandas_used: number
        total_break_minutes: number
        remaining_pandas: number
        remaining_minutes: number
        panda_visualization: string
        last_break?: string
    }
    recentBreaks?: Array<{
        id: number
        break_duration: number
        panda_count: number
        break_timestamp: string
        message_text: string
    }>
    teamBreaks?: Array<{
        user_name: string
        break_duration: number
        panda_count: number
        break_timestamp: string
    }>
    productivityScore?: number
    weeklyStats?: {
        total_pandas: number
        total_break_time: number
        productivity_improvement: number
        team_rank: number
    }
}

const props = withDefaults(defineProps<Props>(), {
    dailyUsage: () => ({
        pandas_used: 0,
        total_break_minutes: 0,
        remaining_pandas: 6,
        remaining_minutes: 60,
        panda_visualization: '⚪⚪⚪⚪⚪⚪',
        last_break: undefined
    }),
    recentBreaks: () => [],
    teamBreaks: () => [],
    productivityScore: 85,
    weeklyStats: () => ({
        total_pandas: 28,
        total_break_time: 420,
        productivity_improvement: 15,
        team_rank: 3
    })
})

const pandaProgress = computed(() => (props.dailyUsage.pandas_used / 6) * 100)
const productivityLevel = computed(() => {
    if (props.productivityScore >= 90) return 'Excellent'
    if (props.productivityScore >= 80) return 'Great'
    if (props.productivityScore >= 70) return 'Good'
    if (props.productivityScore >= 60) return 'Fair'
    return 'Needs Improvement'
})

const takePandaBreak = () => {
    // This would trigger a panda break
    console.log('Taking a panda break! 🐼')
}

const formatTime = (timestamp: string) => {
    return new Date(timestamp).toLocaleTimeString()
}
</script>

<template>
    <Head title="Panda Dashboard" />
    
    <div class="space-y-6">
        <!-- Header -->
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">🐼 Panda Dashboard</h1>
                <p class="text-muted-foreground">Track your productivity with adorable panda breaks</p>
            </div>
            <Button @click="takePandaBreak" class="bg-green-600 hover:bg-green-700">
                <Coffee class="mr-2 h-4 w-4" />
                Take Panda Break
            </Button>
        </div>

        <!-- Daily Overview Cards -->
        <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Pandas Used Today</CardTitle>
                    <span class="text-2xl">🐼</span>
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ dailyUsage.pandas_used }}/6</div>
                    <p class="text-xs text-muted-foreground">
                        {{ dailyUsage.remaining_pandas }} pandas remaining
                    </p>
                    <Progress :value="pandaProgress" class="mt-2" />
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Break Time Today</CardTitle>
                    <Clock class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ dailyUsage.total_break_minutes }}m</div>
                    <p class="text-xs text-muted-foreground">
                        {{ dailyUsage.remaining_minutes }}m remaining
                    </p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Productivity Score</CardTitle>
                    <TrendingUp class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">{{ productivityScore }}%</div>
                    <p class="text-xs text-muted-foreground">
                        {{ productivityLevel }}
                    </p>
                    <Badge :variant="productivityScore >= 80 ? 'default' : 'secondary'" class="mt-1">
                        {{ productivityLevel }}
                    </Badge>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                    <CardTitle class="text-sm font-medium">Team Rank</CardTitle>
                    <Trophy class="h-4 w-4 text-muted-foreground" />
                </CardHeader>
                <CardContent>
                    <div class="text-2xl font-bold">#{{ weeklyStats.team_rank }}</div>
                    <p class="text-xs text-muted-foreground">
                        This week
                    </p>
                </CardContent>
            </Card>
        </div>

        <!-- Panda Visualization -->
        <Card>
            <CardHeader>
                <CardTitle>Today's Panda Progress</CardTitle>
                <CardDescription>Visual representation of your break usage</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="text-center">
                    <div class="text-6xl mb-4 tracking-wider">{{ dailyUsage.panda_visualization }}</div>
                    <p class="text-sm text-muted-foreground">
                        Each 🐼 represents a break taken, ⚪ represents available breaks
                    </p>
                    <div v-if="dailyUsage.last_break" class="mt-2 text-xs text-muted-foreground">
                        Last break: {{ formatTime(dailyUsage.last_break) }}
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Recent Activity -->
        <div class="grid gap-4 md:grid-cols-2">
            <!-- Recent Breaks -->
            <Card>
                <CardHeader>
                    <CardTitle>Recent Breaks</CardTitle>
                    <CardDescription>Your latest panda break sessions</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div v-for="break_item in recentBreaks.slice(0, 5)" :key="break_item.id" class="flex items-center space-x-4">
                            <div class="text-2xl">{{ '🐼'.repeat(break_item.panda_count) }}</div>
                            <div class="flex-1 space-y-1">
                                <p class="text-sm font-medium">{{ break_item.message_text }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ break_item.break_duration }}m • {{ formatTime(break_item.break_timestamp) }}
                                </p>
                            </div>
                        </div>
                        <div v-if="recentBreaks.length === 0" class="text-center text-muted-foreground py-4">
                            No breaks taken today. Time for a panda break! 🐼
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Team Activity -->
            <Card>
                <CardHeader>
                    <CardTitle>Team Activity</CardTitle>
                    <CardDescription>Recent breaks from your team members</CardDescription>
                </CardHeader>
                <CardContent>
                    <div class="space-y-4">
                        <div v-for="team_break in teamBreaks.slice(0, 5)" :key="team_break.user_name + team_break.break_timestamp" class="flex items-center space-x-4">
                            <div class="text-2xl">{{ '🐼'.repeat(team_break.panda_count) }}</div>
                            <div class="flex-1 space-y-1">
                                <p class="text-sm font-medium">{{ team_break.user_name }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ team_break.break_duration }}m break • {{ formatTime(team_break.break_timestamp) }}
                                </p>
                            </div>
                        </div>
                        <div v-if="teamBreaks.length === 0" class="text-center text-muted-foreground py-4">
                            No team activity yet. Encourage your team to take panda breaks! 🐼
                        </div>
                    </div>
                </CardContent>
            </Card>
        </div>

        <!-- Weekly Stats -->
        <Card>
            <CardHeader>
                <CardTitle>Weekly Statistics</CardTitle>
                <CardDescription>Your productivity insights for this week</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="text-center">
                        <div class="text-3xl font-bold text-green-600">{{ weeklyStats.total_pandas }}</div>
                        <p class="text-sm text-muted-foreground">Total Pandas</p>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-blue-600">{{ Math.floor(weeklyStats.total_break_time / 60) }}h {{ weeklyStats.total_break_time % 60 }}m</div>
                        <p class="text-sm text-muted-foreground">Break Time</p>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-purple-600">+{{ weeklyStats.productivity_improvement }}%</div>
                        <p class="text-sm text-muted-foreground">Productivity Boost</p>
                    </div>
                </div>
            </CardContent>
        </Card>

        <!-- Quick Actions -->
        <Card>
            <CardHeader>
                <CardTitle>Quick Actions</CardTitle>
                <CardDescription>Manage your productivity and breaks</CardDescription>
            </CardHeader>
            <CardContent>
                <div class="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm">
                        <Zap class="mr-2 h-4 w-4" />
                        View AI Insights
                    </Button>
                    <Button variant="outline" size="sm">
                        <Users class="mr-2 h-4 w-4" />
                        Team Analytics
                    </Button>
                    <Button variant="outline" size="sm">
                        <Trophy class="mr-2 h-4 w-4" />
                        Achievements
                    </Button>
                    <Button variant="outline" size="sm">
                        <span class="mr-2">🏆</span>
                        NFT Collection
                    </Button>
                </div>
            </CardContent>
        </Card>
    </div>
</template>

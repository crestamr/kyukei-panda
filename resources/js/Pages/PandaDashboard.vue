<template>
    <div class="kyukei-panda-dashboard p-6">
      <!-- Header -->
      <div class="dashboard-header mb-8">
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
          🐼 Kyukei-Panda Break Tracker
        </h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">
          Welcome back, {{ userName }}! Track your breaks and maintain work-life balance.
        </p>
        <div class="text-sm text-gray-500 mt-1">
          {{ formatDate(new Date()) }}
        </div>
      </div>

      <div class="grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <!-- Panda Usage Card -->
        <div class="panda-usage-card bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700">
          <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Today's Panda Usage</h2>

          <div class="panda-counter flex justify-center gap-2 mb-6">
            <span
              v-for="(panda, index) in 6"
              :key="index"
              :class="['text-4xl cursor-pointer transition-transform hover:scale-110',
                       index < pandasUsed ? 'opacity-100' : 'opacity-30']"
              :title="`Panda ${index + 1}: ${index < pandasUsed ? 'Used' : 'Available'}`"
            >
              {{ index < pandasUsed ? '🐼' : '⚪' }}
            </span>
          </div>

          <div class="usage-stats space-y-3 text-sm">
            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
              <span class="text-gray-600 dark:text-gray-400">Break Time:</span>
              <span class="font-semibold text-gray-900 dark:text-white">{{ totalBreakMinutes }}/60 minutes</span>
            </div>
            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
              <span class="text-gray-600 dark:text-gray-400">Pandas Used:</span>
              <span class="font-semibold text-gray-900 dark:text-white">{{ pandasUsed }}/6</span>
            </div>
            <div class="flex justify-between items-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
              <span class="text-gray-600 dark:text-gray-400">Remaining:</span>
              <span class="font-semibold text-green-600 dark:text-green-400">{{ remainingMinutes }} minutes</span>
            </div>
          </div>

          <div class="recommendation mt-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
            <p class="text-sm text-blue-700 dark:text-blue-300">
              {{ getBreakRecommendation() }}
            </p>
          </div>
        </div>

        <!-- Team Overview Card -->
        <div class="team-overview-card bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700">
          <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Team Break Status</h2>

          <div class="team-grid space-y-3">
            <div
              v-for="member in teamBreaks"
              :key="member.id"
              class="team-member-card flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-700 rounded-lg border border-gray-200 dark:border-gray-600"
            >
              <div class="member-info">
                <div class="member-name font-medium text-gray-900 dark:text-white">{{ member.name }}</div>
                <div class="member-time text-sm text-gray-500 dark:text-gray-400">{{ member.total_minutes }} min</div>
              </div>
              <div class="member-pandas text-2xl">
                {{ '🐼'.repeat(member.pandas_used) }}{{ '⚪'.repeat(6 - member.pandas_used) }}
              </div>
            </div>
          </div>

          <div v-if="teamBreaks.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
            <p>No team members found</p>
            <p class="text-sm mt-1">Join a team to see team break status</p>
          </div>
        </div>

        <!-- Break History Card -->
        <div class="break-history-card bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700">
          <h2 class="text-xl font-semibold mb-4 text-gray-900 dark:text-white">Recent Breaks</h2>

          <div class="break-timeline space-y-3 max-h-80 overflow-y-auto">
            <div
              v-for="breakItem in recentBreaks"
              :key="breakItem.id"
              class="break-item flex items-center justify-between p-4 border-l-4 border-blue-500 bg-gray-50 dark:bg-gray-700 rounded-r-lg"
            >
              <div class="break-info">
                <div class="break-time font-medium text-gray-900 dark:text-white">
                  {{ formatTime(breakItem.break_timestamp) }}
                </div>
                <div class="break-channel text-sm text-gray-500 dark:text-gray-400">
                  #{{ breakItem.channel_name }}
                </div>
              </div>
              <div class="break-duration text-right">
                <span class="text-2xl">{{ breakItem.panda_emojis }}</span>
                <div class="text-sm text-gray-500 dark:text-gray-400">({{ breakItem.break_duration }} min)</div>
              </div>
            </div>
          </div>

          <div v-if="recentBreaks.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
            <p>No breaks today</p>
            <p class="text-sm mt-1">Post 🐼 in Slack to record a break!</p>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="quick-actions mt-8 flex flex-wrap gap-4">
        <button
          @click="refreshData"
          class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2"
        >
          🔄 Refresh Data
        </button>

        <button
          @click="openSlackChannel"
          class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2"
        >
          💬 Open Slack
        </button>

        <button
          @click="viewAnalytics"
          class="px-6 py-3 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center gap-2"
        >
          📊 View Analytics
        </button>
      </div>

      <!-- Instructions Card -->
      <div class="instructions-card mt-8 bg-gradient-to-r from-blue-50 to-purple-50 dark:from-blue-900/20 dark:to-purple-900/20 rounded-lg p-6 border border-blue-200 dark:border-blue-800">
        <h3 class="text-lg font-semibold mb-3 text-gray-900 dark:text-white">How to Use Kyukei-Panda</h3>
        <div class="grid md:grid-cols-2 gap-4 text-sm text-gray-700 dark:text-gray-300">
          <div>
            <h4 class="font-medium mb-2">🐼 Recording Breaks:</h4>
            <ul class="space-y-1 list-disc list-inside">
              <li>Post 🐼 emoji in your Slack channel</li>
              <li>Each 🐼 = 10 minutes break time</li>
              <li>Maximum 6 pandas per day (60 minutes)</li>
            </ul>
          </div>
          <div>
            <h4 class="font-medium mb-2">📊 Tracking Benefits:</h4>
            <ul class="space-y-1 list-disc list-inside">
              <li>Improves productivity and focus</li>
              <li>Ensures compliance with labor laws</li>
              <li>Promotes team wellbeing</li>
            </ul>
          </div>
        </div>
      </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'

interface PandaBreak {
  id: number
  panda_count: number
  break_duration: number
  break_timestamp: string
  channel_name: string
  panda_emojis: string
}

interface TeamMember {
  id: number
  name: string
  pandas_used: number
  total_minutes: number
}

interface DailyUsage {
  pandas_used: number
  total_break_minutes: number
  recent_breaks: PandaBreak[]
}

// Props
interface Props {
  userId: number
  userName: string
  teamId?: number
  dailyUsage: DailyUsage
  teamBreaks: TeamMember[]
}

const props = defineProps<Props>()

// Reactive data
const pandasUsed = computed(() => props.dailyUsage?.pandas_used || 0)
const totalBreakMinutes = computed(() => props.dailyUsage?.total_break_minutes || 0)
const recentBreaks = computed(() => props.dailyUsage?.recent_breaks || [])
const remainingMinutes = computed(() => (6 - pandasUsed.value) * 10)

// Methods
const getBreakRecommendation = (): string => {
  const used = pandasUsed.value
  const lastBreak = recentBreaks.value[0]?.break_timestamp
  const timeSinceLastBreak = lastBreak ? 
    Math.floor((Date.now() - new Date(lastBreak).getTime()) / (1000 * 60)) : 0

  if (used === 0) {
    return "🌟 Time for your first break! Post a 🐼 in Slack"
  } else if (used >= 6) {
    return "✅ You've used all your pandas today. Great job taking breaks!"
  } else if (timeSinceLastBreak > 120) {
    return "⏰ It's been 2+ hours since your last break. Consider posting 🐼"
  } else {
    return `💪 ${6 - used} pandas remaining. Next break recommended in ${Math.max(0, 90 - timeSinceLastBreak)} minutes`
  }
}

const formatDate = (date: Date): string => {
  return new Intl.DateTimeFormat('ja-JP', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    weekday: 'long'
  }).format(date)
}

const formatTime = (timestamp: string): string => {
  return new Intl.DateTimeFormat('ja-JP', {
    hour: '2-digit',
    minute: '2-digit'
  }).format(new Date(timestamp))
}

const refreshData = (): void => {
  router.reload({ only: ['dailyUsage', 'teamBreaks'] })
}

const openSlackChannel = (): void => {
  // Open Slack in external browser or app
  window.open('https://slack.com/', '_blank')
}

const viewAnalytics = (): void => {
  router.visit('/panda/analytics')
}

// Lifecycle
onMounted(() => {
  // Set up real-time updates with Laravel Echo
  if (window.Echo) {
    // Listen for panda break events for this user
    window.Echo.private(`user.${props.userId}`)
      .listen('.panda.break.recorded', (event: any) => {
        console.log('Panda break recorded:', event)

        // Update local data
        if (event.daily_status) {
          // Update reactive data with new status
          // This would require making the props reactive or using a store
          refreshData()
        }

        // Show notification
        showNotification(`🐼 Break recorded! ${event.panda_break.panda_emojis}`, 'success')
      })

    // Listen for team panda breaks if user is part of a team
    if (props.teamId) {
      window.Echo.private(`team.${props.teamId}`)
        .listen('.panda.break.recorded', (event: any) => {
          if (event.panda_break.user_name !== props.userName) {
            showNotification(`🐼 ${event.panda_break.user_name} took a break!`, 'info')
            refreshData() // Refresh team data
          }
        })
    }
  }
})

// Helper function to show notifications
const showNotification = (message: string, type: 'success' | 'info' | 'warning' = 'info') => {
  // Simple notification - could be enhanced with a proper notification library
  const notification = document.createElement('div')
  notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
    type === 'success' ? 'bg-green-500' :
    type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'
  } text-white`
  notification.textContent = message

  document.body.appendChild(notification)

  setTimeout(() => {
    notification.remove()
  }, 5000)
}
</script>

<style scoped>
.panda-counter span {
  transition: all 0.3s ease;
}

.panda-counter span:hover {
  transform: scale(1.1);
}

.break-timeline {
  scrollbar-width: thin;
  scrollbar-color: #cbd5e0 #f7fafc;
}

.break-timeline::-webkit-scrollbar {
  width: 6px;
}

.break-timeline::-webkit-scrollbar-track {
  background: #f7fafc;
  border-radius: 3px;
}

.break-timeline::-webkit-scrollbar-thumb {
  background: #cbd5e0;
  border-radius: 3px;
}

.break-timeline::-webkit-scrollbar-thumb:hover {
  background: #a0aec0;
}
</style>

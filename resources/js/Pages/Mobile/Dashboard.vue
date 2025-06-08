<template>
  <div class="mobile-dashboard min-h-screen bg-gray-50 dark:bg-gray-900">
    <!-- Mobile Header -->
    <div class="mobile-header bg-white dark:bg-gray-800 shadow-sm sticky top-0 z-50">
      <div class="flex items-center justify-between p-4">
        <div class="flex items-center gap-3">
          <div class="text-2xl">🐼</div>
          <div>
            <h1 class="text-lg font-semibold text-gray-900 dark:text-white">Kyukei-Panda</h1>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(new Date()) }}</p>
          </div>
        </div>
        
        <div class="flex items-center gap-2">
          <button
            @click="refreshData"
            class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
          >
            🔄
          </button>
          <button
            @click="showMenu = !showMenu"
            class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
          >
            ☰
          </button>
        </div>
      </div>
    </div>

    <!-- Mobile Menu Overlay -->
    <div
      v-if="showMenu"
      class="fixed inset-0 bg-black bg-opacity-50 z-40"
      @click="showMenu = false"
    >
      <div class="absolute right-0 top-0 h-full w-64 bg-white dark:bg-gray-800 shadow-lg">
        <div class="p-4 border-b border-gray-200 dark:border-gray-700">
          <h3 class="font-semibold text-gray-900 dark:text-white">Menu</h3>
        </div>
        <nav class="p-4 space-y-2">
          <a href="/panda/dashboard" class="block py-2 px-3 rounded-lg bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300">
            🐼 Dashboard
          </a>
          <a href="/analytics" class="block py-2 px-3 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
            📊 Analytics
          </a>
          <a href="/teams" class="block py-2 px-3 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
            👥 Teams
          </a>
          <a href="/projects" class="block py-2 px-3 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
            💼 Projects
          </a>
          <a href="/reports" class="block py-2 px-3 rounded-lg text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700">
            📋 Reports
          </a>
        </nav>
      </div>
    </div>

    <!-- Main Content -->
    <div class="mobile-content p-4 space-y-6">
      <!-- Panda Status Card -->
      <div class="panda-status-card bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <div class="text-center mb-6">
          <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">Today's Pandas</h2>
          
          <!-- Panda Visual -->
          <div class="panda-visual flex justify-center gap-1 mb-4">
            <span
              v-for="(panda, index) in 6"
              :key="index"
              :class="['text-3xl transition-transform',
                       index < pandasUsed ? 'opacity-100 animate-bounce' : 'opacity-30']"
              :style="{ animationDelay: `${index * 0.1}s` }"
            >
              {{ index < pandasUsed ? '🐼' : '⚪' }}
            </span>
          </div>

          <!-- Quick Stats -->
          <div class="grid grid-cols-2 gap-4 text-center">
            <div class="stat-item">
              <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">{{ pandasUsed }}</div>
              <div class="text-xs text-gray-500 dark:text-gray-400">Pandas Used</div>
            </div>
            <div class="stat-item">
              <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ totalBreakMinutes }}</div>
              <div class="text-xs text-gray-500 dark:text-gray-400">Break Minutes</div>
            </div>
          </div>
        </div>

        <!-- Recommendation -->
        <div class="recommendation p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
          <p class="text-sm text-blue-700 dark:text-blue-300 text-center">
            {{ getBreakRecommendation() }}
          </p>
        </div>
      </div>

      <!-- Productivity Card -->
      <div class="productivity-card bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Today's Productivity</h3>
        
        <div class="productivity-ring relative w-32 h-32 mx-auto mb-4">
          <!-- SVG Progress Ring -->
          <svg class="w-32 h-32 transform -rotate-90" viewBox="0 0 120 120">
            <circle
              cx="60"
              cy="60"
              r="50"
              stroke="currentColor"
              stroke-width="8"
              fill="none"
              class="text-gray-200 dark:text-gray-700"
            />
            <circle
              cx="60"
              cy="60"
              r="50"
              stroke="currentColor"
              stroke-width="8"
              fill="none"
              stroke-linecap="round"
              class="text-green-500"
              :stroke-dasharray="circumference"
              :stroke-dashoffset="circumference - (productivityScore / 100) * circumference"
              style="transition: stroke-dashoffset 0.5s ease-in-out"
            />
          </svg>
          <div class="absolute inset-0 flex items-center justify-center">
            <div class="text-center">
              <div class="text-2xl font-bold text-gray-900 dark:text-white">{{ Math.round(productivityScore) }}%</div>
              <div class="text-xs text-gray-500 dark:text-gray-400">Score</div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-4 text-center text-sm">
          <div>
            <div class="font-semibold text-gray-900 dark:text-white">{{ formatTime(productiveTime) }}</div>
            <div class="text-gray-500 dark:text-gray-400">Productive</div>
          </div>
          <div>
            <div class="font-semibold text-gray-900 dark:text-white">{{ formatTime(totalTime) }}</div>
            <div class="text-gray-500 dark:text-gray-400">Total</div>
          </div>
        </div>
      </div>

      <!-- Recent Activity Card -->
      <div class="activity-card bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Recent Activity</h3>
        
        <div class="activity-list space-y-3 max-h-64 overflow-y-auto">
          <div
            v-for="activity in recentActivities.slice(0, 5)"
            :key="activity.id"
            class="activity-item flex items-center gap-3 p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"
          >
            <div class="activity-icon text-lg">
              {{ getActivityIcon(activity.category_name) }}
            </div>
            <div class="activity-info flex-1 min-w-0">
              <div class="activity-app text-sm font-medium text-gray-900 dark:text-white truncate">
                {{ activity.application_name }}
              </div>
              <div class="activity-time text-xs text-gray-500 dark:text-gray-400">
                {{ formatTime(activity.duration_seconds) }} • {{ formatRelativeTime(activity.started_at) }}
              </div>
            </div>
            <div class="activity-score">
              <div 
                :class="['w-3 h-3 rounded-full', getProductivityColor(activity.productivity_score)]"
                :title="`Productivity: ${Math.round(activity.productivity_score * 100)}%`"
              ></div>
            </div>
          </div>
        </div>

        <div v-if="recentActivities.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
          <div class="text-3xl mb-2">📱</div>
          <p class="text-sm">No activities tracked yet today</p>
        </div>
      </div>

      <!-- Team Status Card -->
      <div v-if="teamMembers.length > 0" class="team-card bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Team Status</h3>
        
        <div class="team-list space-y-3">
          <div
            v-for="member in teamMembers.slice(0, 4)"
            :key="member.id"
            class="team-member flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded-lg"
          >
            <div class="member-info flex items-center gap-3">
              <div class="member-avatar w-8 h-8 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center text-white text-xs font-medium">
                {{ getInitials(member.name) }}
              </div>
              <div>
                <div class="member-name text-sm font-medium text-gray-900 dark:text-white">{{ member.name }}</div>
                <div class="member-pandas text-xs text-gray-500 dark:text-gray-400">{{ member.pandas_used }} pandas</div>
              </div>
            </div>
            <div class="member-status text-lg">
              {{ '🐼'.repeat(Math.min(member.pandas_used, 3)) }}
            </div>
          </div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="quick-actions grid grid-cols-2 gap-4">
        <button
          @click="openSlackChannel"
          class="action-button bg-green-600 text-white rounded-xl p-4 text-center hover:bg-green-700 transition-colors"
        >
          <div class="text-2xl mb-1">💬</div>
          <div class="text-sm font-medium">Open Slack</div>
        </button>
        
        <button
          @click="viewAnalytics"
          class="action-button bg-purple-600 text-white rounded-xl p-4 text-center hover:bg-purple-700 transition-colors"
        >
          <div class="text-2xl mb-1">📊</div>
          <div class="text-sm font-medium">Analytics</div>
        </button>
      </div>
    </div>

    <!-- Bottom Safe Area -->
    <div class="h-8"></div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'

// Props interface (simplified for mobile)
interface Props {
  userId: number
  userName: string
  dailyUsage: {
    pandas_used: number
    total_break_minutes: number
    productivity_score: number
    productive_time: number
    total_time: number
    recent_activities: Array<any>
  }
  teamMembers: Array<any>
}

const props = defineProps<Props>()

// Reactive data
const showMenu = ref(false)
const circumference = 2 * Math.PI * 50 // For progress ring

// Computed properties
const pandasUsed = computed(() => props.dailyUsage?.pandas_used || 0)
const totalBreakMinutes = computed(() => props.dailyUsage?.total_break_minutes || 0)
const productivityScore = computed(() => props.dailyUsage?.productivity_score || 0)
const productiveTime = computed(() => props.dailyUsage?.productive_time || 0)
const totalTime = computed(() => props.dailyUsage?.total_time || 0)
const recentActivities = computed(() => props.dailyUsage?.recent_activities || [])

// Methods
const getBreakRecommendation = (): string => {
  const used = pandasUsed.value
  if (used === 0) return "🌟 Time for your first break!"
  if (used >= 6) return "✅ All pandas used today!"
  return `💪 ${6 - used} pandas remaining`
}

const formatTime = (seconds: number): string => {
  const hours = Math.floor(seconds / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)
  return hours > 0 ? `${hours}h ${minutes}m` : `${minutes}m`
}

const formatDate = (date: Date): string => {
  return new Intl.DateTimeFormat('ja-JP', {
    month: 'short',
    day: 'numeric',
    weekday: 'short'
  }).format(date)
}

const formatRelativeTime = (timestamp: string): string => {
  const now = new Date()
  const time = new Date(timestamp)
  const diffMinutes = Math.floor((now.getTime() - time.getTime()) / (1000 * 60))
  
  if (diffMinutes < 60) return `${diffMinutes}m ago`
  const diffHours = Math.floor(diffMinutes / 60)
  return `${diffHours}h ago`
}

const getActivityIcon = (category: string): string => {
  const icons: Record<string, string> = {
    'Development': '💻',
    'Design': '🎨',
    'Communication': '💬',
    'Research': '🔍',
    'Entertainment': '🎮',
    'System': '⚙️',
    default: '📱'
  }
  return icons[category] || icons.default
}

const getProductivityColor = (score: number): string => {
  if (score >= 0.7) return 'bg-green-500'
  if (score >= 0.4) return 'bg-yellow-500'
  return 'bg-red-500'
}

const getInitials = (name: string): string => {
  return name.split(' ').map(word => word.charAt(0)).join('').toUpperCase().slice(0, 2)
}

const refreshData = (): void => {
  window.location.reload()
}

const openSlackChannel = (): void => {
  window.open('https://slack.com/', '_blank')
}

const viewAnalytics = (): void => {
  window.location.href = '/analytics'
}

// Lifecycle
onMounted(() => {
  // Add mobile-specific optimizations
  document.body.classList.add('mobile-optimized')
})
</script>

<style scoped>
.mobile-dashboard {
  -webkit-overflow-scrolling: touch;
}

.panda-visual span {
  animation-duration: 2s;
  animation-iteration-count: infinite;
}

.productivity-ring svg {
  filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
}

.activity-list {
  scrollbar-width: none;
  -ms-overflow-style: none;
}

.activity-list::-webkit-scrollbar {
  display: none;
}

.action-button {
  transition: all 0.2s ease;
}

.action-button:active {
  transform: scale(0.95);
}

@media (max-width: 640px) {
  .mobile-content {
    padding: 1rem;
  }
  
  .panda-visual span {
    font-size: 1.5rem;
  }
}
</style>

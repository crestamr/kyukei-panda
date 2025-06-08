<template>
    <div class="analytics-dashboard p-6">
      <!-- Header -->
      <div class="dashboard-header mb-8">
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
              📊 Analytics Dashboard
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">
              Comprehensive insights into your productivity and break patterns
            </p>
          </div>
          
          <!-- Period Selector -->
          <div class="flex gap-4 items-center">
            <select 
              v-model="selectedPeriod" 
              @change="updatePeriod"
              class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-800 text-gray-900 dark:text-white"
            >
              <option value="today">Today</option>
              <option value="week">This Week</option>
              <option value="month">This Month</option>
              <option value="quarter">This Quarter</option>
              <option value="year">This Year</option>
            </select>
            
            <button
              @click="exportData"
              class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2"
            >
              📥 Export
            </button>
          </div>
        </div>
        
        <div class="text-sm text-gray-500 mt-2">
          {{ formatDateRange(dateRange.start, dateRange.end) }}
        </div>
      </div>

      <!-- Key Metrics Cards -->
      <div class="metrics-grid grid gap-6 md:grid-cols-2 lg:grid-cols-4 mb-8">
        <!-- Productivity Score -->
        <div class="metric-card bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 dark:text-gray-400">Productivity Score</p>
              <p class="text-3xl font-bold text-green-600 dark:text-green-400">
                {{ productivity.score.toFixed(1) }}%
              </p>
            </div>
            <div class="text-4xl">📈</div>
          </div>
          <div class="mt-4 text-sm text-gray-500">
            {{ formatTime(productivity.productive_time) }} productive time
          </div>
        </div>

        <!-- Panda Usage -->
        <div class="metric-card bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 dark:text-gray-400">Pandas Used</p>
              <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">
                {{ pandaAnalytics.total_pandas }}
              </p>
            </div>
            <div class="text-4xl">🐼</div>
          </div>
          <div class="mt-4 text-sm text-gray-500">
            {{ pandaAnalytics.total_breaks }} breaks taken
          </div>
        </div>

        <!-- Break Compliance -->
        <div class="metric-card bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 dark:text-gray-400">Break Compliance</p>
              <p class="text-3xl font-bold text-purple-600 dark:text-purple-400">
                {{ pandaAnalytics.compliance_rate.toFixed(1) }}%
              </p>
            </div>
            <div class="text-4xl">✅</div>
          </div>
          <div class="mt-4 text-sm text-gray-500">
            Regular break taking
          </div>
        </div>

        <!-- Total Time -->
        <div class="metric-card bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 dark:text-gray-400">Total Time</p>
              <p class="text-3xl font-bold text-orange-600 dark:text-orange-400">
                {{ formatTime(productivity.total_time) }}
              </p>
            </div>
            <div class="text-4xl">⏱️</div>
          </div>
          <div class="mt-4 text-sm text-gray-500">
            Active working time
          </div>
        </div>
      </div>

      <!-- Charts Row -->
      <div class="charts-grid grid gap-6 lg:grid-cols-2 mb-8">
        <!-- Productivity Breakdown Chart -->
        <div class="chart-card bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Activity Categories</h3>
          <div class="chart-container h-64">
            <canvas ref="categoryChart"></canvas>
          </div>
        </div>

        <!-- Daily Productivity Trend -->
        <div class="chart-card bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Daily Productivity Trend</h3>
          <div class="chart-container h-64">
            <canvas ref="trendChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Break Patterns -->
      <div class="break-patterns-grid grid gap-6 lg:grid-cols-2 mb-8">
        <!-- Break Frequency by Hour -->
        <div class="pattern-card bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Break Patterns by Hour</h3>
          <div class="chart-container h-48">
            <canvas ref="hourlyChart"></canvas>
          </div>
        </div>

        <!-- Break Frequency by Day -->
        <div class="pattern-card bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Break Patterns by Day</h3>
          <div class="chart-container h-48">
            <canvas ref="dailyChart"></canvas>
          </div>
        </div>
      </div>

      <!-- Team Comparison (if available) -->
      <div v-if="teamComparison.length > 0" class="team-comparison mb-8">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Team Comparison</h3>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                  <th class="text-left py-2 text-gray-600 dark:text-gray-400">Team Member</th>
                  <th class="text-right py-2 text-gray-600 dark:text-gray-400">Productivity</th>
                  <th class="text-right py-2 text-gray-600 dark:text-gray-400">Pandas Used</th>
                  <th class="text-right py-2 text-gray-600 dark:text-gray-400">Active Time</th>
                </tr>
              </thead>
              <tbody>
                <tr 
                  v-for="member in teamComparison" 
                  :key="member.user_id"
                  class="border-b border-gray-100 dark:border-gray-700"
                  :class="{ 'bg-blue-50 dark:bg-blue-900/20': member.user_id === user.id }"
                >
                  <td class="py-3 font-medium text-gray-900 dark:text-white">
                    {{ member.name }}
                    <span v-if="member.user_id === user.id" class="text-xs text-blue-600 dark:text-blue-400 ml-2">(You)</span>
                  </td>
                  <td class="text-right py-3">
                    <span class="px-2 py-1 rounded text-xs font-medium"
                          :class="getProductivityColor(member.productivity_score)">
                      {{ member.productivity_score.toFixed(1) }}%
                    </span>
                  </td>
                  <td class="text-right py-3 text-gray-600 dark:text-gray-400">
                    {{ member.total_pandas }}
                  </td>
                  <td class="text-right py-3 text-gray-600 dark:text-gray-400">
                    {{ formatTime(member.total_time) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Daily Breakdown Table -->
      <div class="daily-breakdown">
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-lg p-6 border border-gray-200 dark:border-gray-700">
          <h3 class="text-lg font-semibold mb-4 text-gray-900 dark:text-white">Daily Breakdown</h3>
          <div class="overflow-x-auto">
            <table class="w-full text-sm">
              <thead>
                <tr class="border-b border-gray-200 dark:border-gray-700">
                  <th class="text-left py-2 text-gray-600 dark:text-gray-400">Date</th>
                  <th class="text-right py-2 text-gray-600 dark:text-gray-400">Productivity</th>
                  <th class="text-right py-2 text-gray-600 dark:text-gray-400">Pandas</th>
                  <th class="text-right py-2 text-gray-600 dark:text-gray-400">Activities</th>
                  <th class="text-right py-2 text-gray-600 dark:text-gray-400">Active Time</th>
                </tr>
              </thead>
              <tbody>
                <tr 
                  v-for="day in dailyBreakdown" 
                  :key="day.date"
                  class="border-b border-gray-100 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700"
                >
                  <td class="py-3 font-medium text-gray-900 dark:text-white">
                    {{ formatDate(day.date) }}
                    <div class="text-xs text-gray-500">{{ day.day_name }}</div>
                  </td>
                  <td class="text-right py-3">
                    <span class="px-2 py-1 rounded text-xs font-medium"
                          :class="getProductivityColor(day.productivity_score)">
                      {{ day.productivity_score.toFixed(1) }}%
                    </span>
                  </td>
                  <td class="text-right py-3 text-gray-600 dark:text-gray-400">
                    {{ day.pandas_used }}
                  </td>
                  <td class="text-right py-3 text-gray-600 dark:text-gray-400">
                    {{ day.activities_count }}
                  </td>
                  <td class="text-right py-3 text-gray-600 dark:text-gray-400">
                    {{ formatTime(day.total_time) }}
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted, nextTick } from 'vue'
import { router } from '@inertiajs/vue3'
import Chart from 'chart.js/auto'

// Props interface
interface Props {
  user: {
    id: number
    name: string
    teams: Array<{ id: number; name: string }>
  }
  period: string
  dateRange: {
    start: string
    end: string
  }
  productivity: {
    score: number
    total_time: number
    productive_time: number
    break_time: number
    categories: Record<string, any>
  }
  pandaAnalytics: {
    total_breaks: number
    total_pandas: number
    total_minutes: number
    average_break_length: number
    compliance_rate: number
    breaks_by_day_of_week: Record<string, number>
    breaks_by_hour: Record<string, number>
    daily_usage: Array<any>
  }
  teamComparison: Array<any>
  dailyBreakdown: Array<any>
  activityTrends: {
    categories: Array<any>
    total_activities: number
    total_time: number
    most_productive_category: any
    most_used_category: any
  }
}

const props = defineProps<Props>()

// Reactive data
const selectedPeriod = ref(props.period)

// Chart refs
const categoryChart = ref<HTMLCanvasElement>()
const trendChart = ref<HTMLCanvasElement>()
const hourlyChart = ref<HTMLCanvasElement>()
const dailyChart = ref<HTMLCanvasElement>()

// Methods
const updatePeriod = (): void => {
  router.visit(route('analytics.index'), {
    data: { period: selectedPeriod.value },
    preserveState: true,
  })
}

const exportData = (): void => {
  window.open(route('analytics.export', { period: selectedPeriod.value }), '_blank')
}

const formatTime = (seconds: number): string => {
  const hours = Math.floor(seconds / 3600)
  const minutes = Math.floor((seconds % 3600) / 60)
  
  if (hours > 0) {
    return `${hours}h ${minutes}m`
  } else {
    return `${minutes}m`
  }
}

const formatDate = (dateString: string): string => {
  return new Date(dateString).toLocaleDateString('ja-JP', {
    month: 'short',
    day: 'numeric'
  })
}

const formatDateRange = (start: string, end: string): string => {
  const startDate = new Date(start)
  const endDate = new Date(end)
  
  return `${startDate.toLocaleDateString('ja-JP')} - ${endDate.toLocaleDateString('ja-JP')}`
}

const getProductivityColor = (score: number): string => {
  if (score >= 80) return 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400'
  if (score >= 60) return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400'
  return 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400'
}

// Chart initialization
const initializeCharts = (): void => {
  // Category breakdown chart
  if (categoryChart.value) {
    new Chart(categoryChart.value, {
      type: 'doughnut',
      data: {
        labels: Object.keys(props.productivity.categories),
        datasets: [{
          data: Object.values(props.productivity.categories).map((cat: any) => cat.percentage),
          backgroundColor: [
            '#10B981', '#3B82F6', '#8B5CF6', '#F59E0B', '#EF4444', '#6B7280'
          ]
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom'
          }
        }
      }
    })
  }

  // Daily trend chart
  if (trendChart.value) {
    new Chart(trendChart.value, {
      type: 'line',
      data: {
        labels: props.dailyBreakdown.map(day => formatDate(day.date)),
        datasets: [{
          label: 'Productivity Score',
          data: props.dailyBreakdown.map(day => day.productivity_score),
          borderColor: '#3B82F6',
          backgroundColor: '#3B82F6',
          tension: 0.4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            max: 100
          }
        }
      }
    })
  }
}

// Lifecycle
onMounted(() => {
  nextTick(() => {
    initializeCharts()
  })
})
</script>

<style scoped>
.chart-container {
  position: relative;
}

.metric-card:hover {
  transform: translateY(-2px);
  transition: transform 0.2s ease;
}

.chart-card {
  transition: all 0.3s ease;
}

.chart-card:hover {
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}
</style>

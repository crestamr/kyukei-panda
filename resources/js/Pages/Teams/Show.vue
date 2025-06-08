<template>
    <div class="team-show p-6">
      <!-- Header -->
      <div class="header-section mb-8">
        <div class="flex justify-between items-start">
          <div>
            <div class="flex items-center gap-3 mb-2">
              <router-link
                :href="route('teams.index')"
                class="text-gray-400 hover:text-gray-600 transition-colors"
              >
                ← Back to Teams
              </router-link>
            </div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
              👥 {{ team.name }}
            </h1>
            <p v-if="team.description" class="text-gray-600 dark:text-gray-400 mt-2">
              {{ team.description }}
            </p>
            <div class="flex items-center gap-3 mt-3">
              <span 
                class="px-3 py-1 rounded-full text-sm font-medium"
                :class="getRoleBadgeClass(userRole)"
              >
                Your Role: {{ userRole }}
              </span>
              <span 
                v-if="team.is_active"
                class="px-3 py-1 bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400 rounded-full text-sm font-medium"
              >
                Active Team
              </span>
            </div>
          </div>
          
          <div class="flex gap-3">
            <button
              v-if="['admin', 'manager'].includes(userRole)"
              @click="showInviteModal = true"
              class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center gap-2"
            >
              ➕ Invite Member
            </button>
            <button
              v-if="['admin', 'manager'].includes(userRole)"
              @click="openSettings"
              class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors flex items-center gap-2"
            >
              ⚙️ Settings
            </button>
          </div>
        </div>
      </div>

      <!-- Stats Cards -->
      <div class="stats-grid grid gap-6 md:grid-cols-4 mb-8">
        <div class="stat-card bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 dark:text-gray-400">Total Members</p>
              <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ stats.total_members }}</p>
            </div>
            <div class="text-4xl">👤</div>
          </div>
        </div>

        <div class="stat-card bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 dark:text-gray-400">Active Projects</p>
              <p class="text-3xl font-bold text-green-600 dark:text-green-400">{{ stats.active_projects }}</p>
            </div>
            <div class="text-4xl">📊</div>
          </div>
        </div>

        <div class="stat-card bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 dark:text-gray-400">Clients</p>
              <p class="text-3xl font-bold text-purple-600 dark:text-purple-400">{{ stats.total_clients }}</p>
            </div>
            <div class="text-4xl">🏢</div>
          </div>
        </div>

        <div class="stat-card bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 dark:text-gray-400">Categories</p>
              <p class="text-3xl font-bold text-orange-600 dark:text-orange-400">{{ stats.total_categories }}</p>
            </div>
            <div class="text-4xl">🏷️</div>
          </div>
        </div>
      </div>

      <!-- Main Content Tabs -->
      <div class="content-tabs mb-8">
        <div class="border-b border-gray-200 dark:border-gray-700">
          <nav class="-mb-px flex space-x-8">
            <button
              v-for="tab in tabs"
              :key="tab.id"
              @click="activeTab = tab.id"
              :class="[
                'py-2 px-1 border-b-2 font-medium text-sm',
                activeTab === tab.id
                  ? 'border-blue-500 text-blue-600 dark:text-blue-400'
                  : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'
              ]"
            >
              {{ tab.icon }} {{ tab.name }}
            </button>
          </nav>
        </div>
      </div>

      <!-- Tab Content -->
      <div class="tab-content">
        <!-- Members Tab -->
        <div v-if="activeTab === 'members'" class="members-section">
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Team Members</h3>
            </div>
            
            <div class="overflow-x-auto">
              <table class="w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                  <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                      Member
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                      Role
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                      Joined
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                      Slack
                    </th>
                    <th v-if="['admin', 'manager'].includes(userRole)" class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                      Actions
                    </th>
                  </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                  <tr v-for="member in team.members" :key="member.id">
                    <td class="px-6 py-4 whitespace-nowrap">
                      <div class="flex items-center">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center text-white font-medium">
                          {{ getInitials(member.name) }}
                        </div>
                        <div class="ml-4">
                          <div class="text-sm font-medium text-gray-900 dark:text-white">
                            {{ member.name }}
                            <span v-if="member.id === user.id" class="text-xs text-blue-600 dark:text-blue-400 ml-2">(You)</span>
                          </div>
                          <div class="text-sm text-gray-500 dark:text-gray-400">{{ member.email }}</div>
                        </div>
                      </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                      <span 
                        class="px-2 py-1 rounded-full text-xs font-medium"
                        :class="getRoleBadgeClass(member.role)"
                      >
                        {{ member.role }}
                      </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                      {{ formatDate(member.joined_at) }}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                      <span v-if="member.slack_username" class="flex items-center gap-1">
                        💬 @{{ member.slack_username }}
                      </span>
                      <span v-else class="text-gray-400">Not connected</span>
                    </td>
                    <td v-if="['admin', 'manager'].includes(userRole)" class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                      <div class="flex justify-end gap-2">
                        <button
                          v-if="userRole === 'admin' && member.id !== user.id"
                          @click="changeRole(member)"
                          class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                        >
                          Change Role
                        </button>
                        <button
                          v-if="member.id !== user.id"
                          @click="removeMember(member)"
                          class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                        >
                          Remove
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Projects Tab -->
        <div v-if="activeTab === 'projects'" class="projects-section">
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700 flex justify-between items-center">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Projects</h3>
              <button
                v-if="['admin', 'manager'].includes(userRole)"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
              >
                ➕ New Project
              </button>
            </div>
            
            <div class="grid gap-4 p-6">
              <div
                v-for="project in team.projects"
                :key="project.id"
                class="project-card p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:shadow-md transition-shadow"
              >
                <div class="flex items-start justify-between">
                  <div class="flex-1">
                    <div class="flex items-center gap-3 mb-2">
                      <div 
                        class="w-4 h-4 rounded-full"
                        :style="{ backgroundColor: project.color }"
                      ></div>
                      <h4 class="font-medium text-gray-900 dark:text-white">{{ project.name }}</h4>
                      <span 
                        v-if="project.is_active"
                        class="px-2 py-1 bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400 rounded-full text-xs"
                      >
                        Active
                      </span>
                    </div>
                    <p v-if="project.description" class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                      {{ project.description }}
                    </p>
                    <div class="flex items-center gap-4 text-sm text-gray-500 dark:text-gray-400">
                      <span v-if="project.client_name">🏢 {{ project.client_name }}</span>
                      <span v-if="project.hourly_rate">💰 ¥{{ project.hourly_rate }}/hour</span>
                    </div>
                  </div>
                  <div class="flex gap-2">
                    <button class="p-2 text-gray-400 hover:text-blue-600 transition-colors">
                      👁️
                    </button>
                    <button 
                      v-if="['admin', 'manager'].includes(userRole)"
                      class="p-2 text-gray-400 hover:text-gray-600 transition-colors"
                    >
                      ✏️
                    </button>
                  </div>
                </div>
              </div>
              
              <div v-if="team.projects.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                <div class="text-4xl mb-2">📊</div>
                <p>No projects yet. Create your first project to get started!</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Activity Tab -->
        <div v-if="activeTab === 'activity'" class="activity-section">
          <div class="bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
              <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Team Activity</h3>
            </div>
            
            <div class="p-6">
              <div class="space-y-4">
                <div
                  v-for="activity in recentActivity"
                  :key="activity.id"
                  class="activity-item flex items-center gap-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg"
                >
                  <div class="text-2xl">🐼</div>
                  <div class="flex-1">
                    <p class="text-sm font-medium text-gray-900 dark:text-white">
                      {{ activity.user_name }} took a {{ activity.break_duration }} minute break
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                      {{ formatDateTime(activity.break_timestamp) }} in #{{ activity.channel_name }}
                    </p>
                  </div>
                  <div class="text-lg">
                    {{ '🐼'.repeat(activity.panda_count) }}
                  </div>
                </div>
              </div>
              
              <div v-if="recentActivity.length === 0" class="text-center py-8 text-gray-500 dark:text-gray-400">
                <div class="text-4xl mb-2">🐼</div>
                <p>No recent panda break activity. Start using 🐼 in Slack!</p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Invite Modal -->
      <div v-if="showInviteModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white dark:bg-gray-800 rounded-lg p-6 w-full max-w-md">
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Invite Team Member</h3>
          
          <form @submit.prevent="inviteMember">
            <div class="mb-4">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Email Address
              </label>
              <input
                v-model="inviteForm.email"
                type="email"
                required
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                placeholder="colleague@company.com"
              />
            </div>
            
            <div class="mb-6">
              <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Role
              </label>
              <select
                v-model="inviteForm.role"
                class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
              >
                <option value="member">Member</option>
                <option value="manager">Manager</option>
                <option v-if="userRole === 'admin'" value="admin">Admin</option>
              </select>
            </div>
            
            <div class="flex gap-3">
              <button
                type="submit"
                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
              >
                Send Invitation
              </button>
              <button
                type="button"
                @click="showInviteModal = false"
                class="px-4 py-2 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors"
              >
                Cancel
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'

// Props and interfaces would be defined here...
// This is a truncated version due to length constraints

const activeTab = ref('members')
const showInviteModal = ref(false)
const inviteForm = ref({
  email: '',
  role: 'member'
})

const tabs = [
  { id: 'members', name: 'Members', icon: '👤' },
  { id: 'projects', name: 'Projects', icon: '📊' },
  { id: 'activity', name: 'Activity', icon: '🐼' },
]

// Methods would be implemented here...
</script>

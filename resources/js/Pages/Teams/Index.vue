<template>
    <div class="teams-index p-6">
      <!-- Header -->
      <div class="header-section mb-8">
        <div class="flex justify-between items-center">
          <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
              👥 My Teams
            </h1>
            <p class="text-gray-600 dark:text-gray-400 mt-2">
              Manage your teams and collaborate with colleagues
            </p>
          </div>
          
          <router-link
            :href="route('teams.create')"
            class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2"
          >
            ➕ Create Team
          </router-link>
        </div>
      </div>

      <!-- Teams Grid -->
      <div v-if="teams.length > 0" class="teams-grid grid gap-6 md:grid-cols-2 lg:grid-cols-3">
        <div
          v-for="team in teams"
          :key="team.id"
          class="team-card bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 hover:shadow-xl transition-all duration-300"
        >
          <!-- Team Header -->
          <div class="team-header p-6 border-b border-gray-200 dark:border-gray-700">
            <div class="flex items-start justify-between">
              <div class="flex-1">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
                  {{ team.name }}
                </h3>
                <p v-if="team.description" class="text-gray-600 dark:text-gray-400 text-sm mb-3">
                  {{ team.description }}
                </p>
                <div class="flex items-center gap-2">
                  <span 
                    class="px-2 py-1 rounded-full text-xs font-medium"
                    :class="getRoleBadgeClass(team.user_role)"
                  >
                    {{ team.user_role }}
                  </span>
                  <span 
                    v-if="team.is_active"
                    class="px-2 py-1 bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400 rounded-full text-xs font-medium"
                  >
                    Active
                  </span>
                </div>
              </div>
              
              <div class="flex items-center gap-2">
                <router-link
                  :href="route('teams.show', team.id)"
                  class="p-2 text-gray-400 hover:text-blue-600 transition-colors"
                  title="View Team"
                >
                  👁️
                </router-link>
                <button
                  v-if="['admin', 'manager'].includes(team.user_role)"
                  @click="openTeamSettings(team)"
                  class="p-2 text-gray-400 hover:text-gray-600 transition-colors"
                  title="Team Settings"
                >
                  ⚙️
                </button>
              </div>
            </div>
          </div>

          <!-- Team Stats -->
          <div class="team-stats p-6">
            <div class="grid grid-cols-3 gap-4 text-center">
              <div class="stat-item">
                <div class="text-2xl font-bold text-blue-600 dark:text-blue-400">
                  {{ team.users_count }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Members</div>
              </div>
              <div class="stat-item">
                <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                  {{ team.projects_count }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Projects</div>
              </div>
              <div class="stat-item">
                <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                  {{ team.clients_count }}
                </div>
                <div class="text-xs text-gray-500 dark:text-gray-400">Clients</div>
              </div>
            </div>
          </div>

          <!-- Team Members Preview -->
          <div class="team-members p-6 pt-0">
            <div class="flex items-center justify-between mb-3">
              <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Recent Members</h4>
              <span class="text-xs text-gray-500">{{ team.members.length }} total</span>
            </div>
            
            <div class="flex -space-x-2">
              <div
                v-for="(member, index) in team.members.slice(0, 5)"
                :key="member.id"
                class="relative"
              >
                <div
                  class="w-8 h-8 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center text-white text-xs font-medium border-2 border-white dark:border-gray-800"
                  :title="member.name"
                >
                  {{ getInitials(member.name) }}
                </div>
              </div>
              
              <div
                v-if="team.members.length > 5"
                class="w-8 h-8 rounded-full bg-gray-300 dark:bg-gray-600 flex items-center justify-center text-gray-600 dark:text-gray-300 text-xs font-medium border-2 border-white dark:border-gray-800"
                :title="`+${team.members.length - 5} more members`"
              >
                +{{ team.members.length - 5 }}
              </div>
            </div>
          </div>

          <!-- Team Actions -->
          <div class="team-actions p-6 pt-0">
            <div class="flex gap-2">
              <router-link
                :href="route('teams.show', team.id)"
                class="flex-1 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors text-center text-sm font-medium"
              >
                View Details
              </router-link>
              
              <button
                v-if="['admin', 'manager'].includes(team.user_role)"
                @click="openInviteModal(team)"
                class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors text-sm font-medium"
                title="Invite Members"
              >
                ➕
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="empty-state text-center py-16">
        <div class="text-6xl mb-4">👥</div>
        <h3 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">
          No Teams Yet
        </h3>
        <p class="text-gray-600 dark:text-gray-400 mb-6 max-w-md mx-auto">
          Create your first team to start collaborating with colleagues and tracking productivity together.
        </p>
        <router-link
          :href="route('teams.create')"
          class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
        >
          ➕ Create Your First Team
        </router-link>
      </div>

      <!-- Quick Stats -->
      <div v-if="teams.length > 0" class="quick-stats mt-12 grid gap-6 md:grid-cols-4">
        <div class="stat-card bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 dark:text-gray-400">Total Teams</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ teams.length }}</p>
            </div>
            <div class="text-3xl">👥</div>
          </div>
        </div>

        <div class="stat-card bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 dark:text-gray-400">Admin Roles</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ adminTeamsCount }}</p>
            </div>
            <div class="text-3xl">👑</div>
          </div>
        </div>

        <div class="stat-card bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 dark:text-gray-400">Total Members</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ totalMembersCount }}</p>
            </div>
            <div class="text-3xl">👤</div>
          </div>
        </div>

        <div class="stat-card bg-white dark:bg-gray-800 rounded-lg shadow p-6 border border-gray-200 dark:border-gray-700">
          <div class="flex items-center justify-between">
            <div>
              <p class="text-sm text-gray-600 dark:text-gray-400">Total Projects</p>
              <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ totalProjectsCount }}</p>
            </div>
            <div class="text-3xl">📊</div>
          </div>
        </div>
      </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'

interface TeamMember {
  id: number
  name: string
  email: string
  role: string
  joined_at: string
}

interface Team {
  id: number
  name: string
  slug: string
  description: string
  is_active: boolean
  users_count: number
  projects_count: number
  clients_count: number
  user_role: string
  joined_at: string
  members: TeamMember[]
}

interface Props {
  teams: Team[]
  user: {
    id: number
    name: string
    email: string
  }
}

const props = defineProps<Props>()

// Computed properties
const adminTeamsCount = computed(() => 
  props.teams.filter(team => team.user_role === 'admin').length
)

const totalMembersCount = computed(() => 
  props.teams.reduce((total, team) => total + team.users_count, 0)
)

const totalProjectsCount = computed(() => 
  props.teams.reduce((total, team) => total + team.projects_count, 0)
)

// Methods
const getRoleBadgeClass = (role: string): string => {
  switch (role) {
    case 'admin':
      return 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400'
    case 'manager':
      return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400'
    case 'member':
      return 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400'
    default:
      return 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400'
  }
}

const getInitials = (name: string): string => {
  return name
    .split(' ')
    .map(word => word.charAt(0))
    .join('')
    .toUpperCase()
    .slice(0, 2)
}

const openTeamSettings = (team: Team): void => {
  router.visit(route('teams.edit', team.id))
}

const openInviteModal = (team: Team): void => {
  // This would open a modal or navigate to invite page
  router.visit(route('teams.show', team.id), {
    data: { action: 'invite' }
  })
}
</script>

<style scoped>
.team-card:hover {
  transform: translateY(-2px);
}

.stat-card:hover {
  transform: translateY(-1px);
  transition: transform 0.2s ease;
}

.team-members .relative:hover {
  z-index: 10;
  transform: scale(1.1);
  transition: transform 0.2s ease;
}
</style>

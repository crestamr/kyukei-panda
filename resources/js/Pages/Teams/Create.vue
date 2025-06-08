<template>
    <div class="team-create p-6 max-w-2xl mx-auto">
      <!-- Header -->
      <div class="header-section mb-8">
        <div class="flex items-center gap-3 mb-4">
          <router-link
            :href="route('teams.index')"
            class="text-gray-400 hover:text-gray-600 transition-colors"
          >
            ← Back to Teams
          </router-link>
        </div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white flex items-center gap-3">
          ➕ Create New Team
        </h1>
        <p class="text-gray-600 dark:text-gray-400 mt-2">
          Set up a new team to collaborate with colleagues and track productivity together.
        </p>
      </div>

      <!-- Create Form -->
      <div class="form-section bg-white dark:bg-gray-800 rounded-lg shadow border border-gray-200 dark:border-gray-700">
        <div class="p-6">
          <form @submit.prevent="createTeam">
            <!-- Team Name -->
            <div class="mb-6">
              <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Team Name *
              </label>
              <input
                id="name"
                v-model="form.name"
                type="text"
                required
                class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                placeholder="e.g., Development Team, Marketing Squad"
              />
              <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Choose a descriptive name for your team
              </p>
            </div>

            <!-- Team Description -->
            <div class="mb-6">
              <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                Description
              </label>
              <textarea
                id="description"
                v-model="form.description"
                rows="4"
                class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                placeholder="Describe what this team does and its purpose..."
              ></textarea>
              <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                Optional: Help team members understand the team's purpose
              </p>
            </div>

            <!-- Team Features Preview -->
            <div class="mb-8 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-200 dark:border-blue-800">
              <h3 class="text-sm font-medium text-blue-900 dark:text-blue-300 mb-3">
                🎉 Your team will get access to:
              </h3>
              <div class="grid md:grid-cols-2 gap-3 text-sm text-blue-800 dark:text-blue-300">
                <div class="flex items-center gap-2">
                  <span>🐼</span>
                  <span>Kyukei-Panda break tracking</span>
                </div>
                <div class="flex items-center gap-2">
                  <span>📊</span>
                  <span>Team productivity analytics</span>
                </div>
                <div class="flex items-center gap-2">
                  <span>👥</span>
                  <span>Member management</span>
                </div>
                <div class="flex items-center gap-2">
                  <span>💼</span>
                  <span>Project & client tracking</span>
                </div>
                <div class="flex items-center gap-2">
                  <span>💬</span>
                  <span>Slack integration</span>
                </div>
                <div class="flex items-center gap-2">
                  <span>📈</span>
                  <span>Real-time dashboards</span>
                </div>
              </div>
            </div>

            <!-- Form Actions -->
            <div class="flex gap-4">
              <button
                type="submit"
                :disabled="processing"
                class="flex-1 px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition-colors font-medium"
              >
                <span v-if="processing">Creating Team...</span>
                <span v-else>✨ Create Team</span>
              </button>
              
              <router-link
                :href="route('teams.index')"
                class="px-6 py-3 bg-gray-300 dark:bg-gray-600 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-500 transition-colors font-medium text-center"
              >
                Cancel
              </router-link>
            </div>
          </form>
        </div>
      </div>

      <!-- Next Steps Preview -->
      <div class="next-steps mt-8 bg-gray-50 dark:bg-gray-800 rounded-lg p-6 border border-gray-200 dark:border-gray-700">
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">
          🚀 After creating your team:
        </h3>
        <div class="space-y-3 text-sm text-gray-600 dark:text-gray-400">
          <div class="flex items-start gap-3">
            <span class="flex-shrink-0 w-6 h-6 bg-blue-100 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center text-xs font-medium">1</span>
            <div>
              <p class="font-medium text-gray-900 dark:text-white">Invite team members</p>
              <p>Add colleagues by email and assign appropriate roles</p>
            </div>
          </div>
          <div class="flex items-start gap-3">
            <span class="flex-shrink-0 w-6 h-6 bg-blue-100 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center text-xs font-medium">2</span>
            <div>
              <p class="font-medium text-gray-900 dark:text-white">Set up Slack integration</p>
              <p>Connect your Slack workspace for panda break tracking</p>
            </div>
          </div>
          <div class="flex items-start gap-3">
            <span class="flex-shrink-0 w-6 h-6 bg-blue-100 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center text-xs font-medium">3</span>
            <div>
              <p class="font-medium text-gray-900 dark:text-white">Create projects and clients</p>
              <p>Organize your work with projects and client management</p>
            </div>
          </div>
          <div class="flex items-start gap-3">
            <span class="flex-shrink-0 w-6 h-6 bg-blue-100 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400 rounded-full flex items-center justify-center text-xs font-medium">4</span>
            <div>
              <p class="font-medium text-gray-900 dark:text-white">Start tracking productivity</p>
              <p>Begin using 🐼 emojis in Slack and monitor team analytics</p>
            </div>
          </div>
        </div>
      </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

// Form data
const form = ref({
  name: '',
  description: ''
})

const processing = ref(false)

// Methods
const createTeam = (): void => {
  if (!form.value.name.trim()) {
    return
  }

  processing.value = true

  router.post(route('teams.store'), form.value, {
    onSuccess: () => {
      // Success handled by redirect
    },
    onError: (errors) => {
      console.error('Team creation failed:', errors)
    },
    onFinish: () => {
      processing.value = false
    }
  })
}
</script>

<style scoped>
.form-section {
  transition: all 0.3s ease;
}

.form-section:hover {
  box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

input:focus,
textarea:focus {
  transform: translateY(-1px);
  transition: transform 0.2s ease;
}
</style>

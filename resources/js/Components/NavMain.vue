<script lang="ts" setup>
import {
    SidebarGroup,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem
} from '@/Components/ui/sidebar'
import { Link, router, usePage } from '@inertiajs/vue3'
import {
    AppWindowMac,
    ChartColumnBig,
    Cog,
    FileChartColumn,
    FileClock,
    TentTree,
    Brain,
    Coins,
    Wifi,
    Rocket,
    Users,
    Trophy,
    BarChart3,
    Zap,
    Globe,
    Shield,
    Smartphone,
    Bot
} from 'lucide-vue-next'
import moment from 'moment/min/moment-with-locales'
import { ref } from 'vue'

const date = ref(moment(usePage().props.date ?? undefined, 'DD.MM.YYYY').format('YYYY-MM-DD'))
const current = ref(route().current())

router.on('navigate', () => {
    date.value = moment(usePage().props.date ?? undefined, 'DD.MM.YYYY').format('YYYY-MM-DD')
    current.value = route().current()
})

const showComingSoon = (feature: string) => {
    alert(`🐼 ${feature} is coming soon! This feature is part of our advanced AI platform and will be available in the next update.`)
}

const checkNativeStatus = async () => {
    try {
        const response = await fetch('/api/native/status')
        const data = await response.json()
        alert(`🐼 Native App Status:\n\nAvailable: ${data.status.available ? 'Yes' : 'No'}\nPlatform: ${data.platform.platform}\nElectron: ${data.platform.electron_version || 'Not installed'}`)
    } catch (error) {
        alert('🐼 Native app status check failed. The native app might not be running yet.')
    }
}
</script>

<template>
    <SidebarGroup>
        <SidebarMenu>
            <SidebarMenuItem>
                <SidebarMenuButton as-child>
                    <Link :href="route('home')" prefetch>
                        <ChartColumnBig />
                        {{ $t('app.overview') }}
                    </Link>
                </SidebarMenuButton>
                <SidebarMenuSub>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <Link
                                :class="{
                                    'text-primary! font-bold': [
                                        'overview.day.show',
                                        'timestamp.create',
                                        'timestamp.edit'
                                    ].includes(current ?? '')
                                }"
                                :href="route('overview.day.show', { date })"
                                class="transition-all duration-200"
                                prefetch
                            >
                                {{ $t('app.day') }}
                            </Link>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <Link
                                :class="{
                                    'text-primary! font-bold': current === 'overview.week.show'
                                }"
                                :href="route('overview.week.show', { date })"
                                class="transition-all duration-200"
                                prefetch
                            >
                                {{ $t('app.week') }}
                            </Link>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <Link
                                :class="{
                                    'text-primary! font-bold': current === 'overview.month.show'
                                }"
                                :href="route('overview.month.show', { date })"
                                class="transition-all duration-200"
                                prefetch
                            >
                                {{ $t('app.month') }}
                            </Link>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <Link
                                :class="{
                                    'text-primary! font-bold': current === 'overview.year.show'
                                }"
                                :href="route('overview.year.show', { date })"
                                class="transition-all duration-200"
                                prefetch
                            >
                                {{ $t('app.year') }}
                            </Link>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                </SidebarMenuSub>
            </SidebarMenuItem>
            <SidebarMenuItem>
                <SidebarMenuButton as-child>
                    <Link
                        :class="{
                            'text-primary! font-bold': current === 'app-activity.index'
                        }"
                        :href="route('app-activity.index')"
                        class="transition-all duration-200"
                        prefetch
                    >
                        <AppWindowMac />
                        {{ $t('app.app activities') }}
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
            <SidebarMenuItem>
                <SidebarMenuButton as-child>
                    <Link
                        :class="{
                            'text-primary! font-bold': current === 'absence.show'
                        }"
                        :href="route('absence.show', { date })"
                        class="transition-all duration-200"
                        prefetch
                    >
                        <TentTree />
                        {{ $t('app.absences and leave') }}
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
            <SidebarMenuItem>
                <SidebarMenuButton as-child>
                    <Link
                        :class="{
                            'text-primary! font-bold': ['work-schedule.index', 'work-schedule.edit'].includes(
                                current ?? ''
                            )
                        }"
                        :href="route('work-schedule.index')"
                        class="transition-all duration-200"
                        prefetch
                    >
                        <FileClock />
                        {{ $t('app.work schedule') }}
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
            <SidebarMenuItem>
                <SidebarMenuButton as-child>
                    <Link
                        :class="{
                            'text-primary! font-bold': ['import-export.index', 'import.clockify.create'].includes(
                                current ?? ''
                            )
                        }"
                        :href="route('import-export.index')"
                        class="transition-all duration-200"
                        prefetch
                    >
                        <FileChartColumn />
                        {{ $t('app.import / export') }}
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
            <!-- Kyukei-Panda Features -->
            <SidebarMenuItem>
                <SidebarMenuButton as-child>
                    <Link
                        :class="{
                            'text-primary! font-bold': current === 'panda.dashboard'
                        }"
                        :href="route('panda.dashboard')"
                        class="transition-all duration-200"
                        prefetch
                    >
                        <span class="text-lg">🐼</span>
                        Panda Dashboard
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>

            <SidebarMenuItem>
                <SidebarMenuButton as-child>
                    <Link
                        :href="route('teams.index')"
                        class="transition-all duration-200"
                        prefetch
                    >
                        <Users />
                        Team Collaboration
                    </Link>
                </SidebarMenuButton>
                <SidebarMenuSub>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <Link :href="route('teams.index')" class="transition-all duration-200" prefetch>
                                Teams
                            </Link>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <a href="#" @click.prevent="showComingSoon('Projects')" class="transition-all duration-200">
                                Projects
                            </a>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <a href="#" @click.prevent="showComingSoon('Clients')" class="transition-all duration-200">
                                Clients
                            </a>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                </SidebarMenuSub>
            </SidebarMenuItem>

            <SidebarMenuItem>
                <SidebarMenuButton as-child>
                    <Link
                        :href="route('analytics.index')"
                        class="transition-all duration-200"
                        prefetch
                    >
                        <BarChart3 />
                        Analytics & Insights
                    </Link>
                </SidebarMenuButton>
                <SidebarMenuSub>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <Link :href="route('analytics.dashboard')" class="transition-all duration-200" prefetch>
                                Dashboard
                            </Link>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <Link :href="route('analytics.team')" class="transition-all duration-200" prefetch>
                                Team Analytics
                            </Link>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <Link :href="route('analytics.productivity')" class="transition-all duration-200" prefetch>
                                Productivity Reports
                            </Link>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                </SidebarMenuSub>
            </SidebarMenuItem>

            <SidebarMenuItem>
                <SidebarMenuButton as-child>
                    <a
                        href="#"
                        class="transition-all duration-200 flex items-center space-x-2 px-2 py-1.5 text-sm rounded-md hover:bg-accent hover:text-accent-foreground"
                        @click.prevent="showComingSoon('AI Features')"
                    >
                        <Brain />
                        <span>AI Features</span>
                    </a>
                </SidebarMenuButton>
                <SidebarMenuSub>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <a href="#" @click.prevent="showComingSoon('AI Insights')" class="transition-all duration-200">
                                AI Insights
                            </a>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <a href="#" @click.prevent="showComingSoon('Productivity Prediction')" class="transition-all duration-200">
                                Productivity Prediction
                            </a>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <a href="#" @click.prevent="showComingSoon('Workspace Analysis')" class="transition-all duration-200">
                                Workspace Analysis
                            </a>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                </SidebarMenuSub>
            </SidebarMenuItem>

            <SidebarMenuItem>
                <SidebarMenuButton as-child>
                    <a
                        href="#"
                        class="transition-all duration-200 flex items-center space-x-2 px-2 py-1.5 text-sm rounded-md hover:bg-accent hover:text-accent-foreground"
                        @click.prevent="showComingSoon('Blockchain & Web3')"
                    >
                        <Coins />
                        <span>Blockchain & Web3</span>
                    </a>
                </SidebarMenuButton>
                <SidebarMenuSub>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <a href="#" @click.prevent="showComingSoon('NFT Achievements')" class="transition-all duration-200">
                                NFT Achievements
                            </a>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <a href="#" @click.prevent="showComingSoon('PANDA Tokens')" class="transition-all duration-200">
                                PANDA Tokens
                            </a>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <a href="#" @click.prevent="showComingSoon('DAO Governance')" class="transition-all duration-200">
                                DAO Governance
                            </a>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                </SidebarMenuSub>
            </SidebarMenuItem>

            <SidebarMenuItem>
                <SidebarMenuButton as-child>
                    <a
                        href="#"
                        class="transition-all duration-200 flex items-center space-x-2 px-2 py-1.5 text-sm rounded-md hover:bg-accent hover:text-accent-foreground"
                        @click.prevent="showComingSoon('IoT & Smart Workspace')"
                    >
                        <Wifi />
                        <span>IoT & Smart Workspace</span>
                    </a>
                </SidebarMenuButton>
                <SidebarMenuSub>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <a href="#" @click.prevent="showComingSoon('Smart Desk')" class="transition-all duration-200">
                                Smart Desk
                            </a>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <a href="#" @click.prevent="showComingSoon('Smart Lighting')" class="transition-all duration-200">
                                Smart Lighting
                            </a>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <a href="#" @click.prevent="showComingSoon('Environmental Sensors')" class="transition-all duration-200">
                                Environmental Sensors
                            </a>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                </SidebarMenuSub>
            </SidebarMenuItem>

            <SidebarMenuItem>
                <SidebarMenuButton as-child>
                    <a
                        href="#"
                        class="transition-all duration-200 flex items-center space-x-2 px-2 py-1.5 text-sm rounded-md hover:bg-accent hover:text-accent-foreground"
                        @click.prevent="showComingSoon('Future Technologies')"
                    >
                        <Rocket />
                        <span>Future Technologies</span>
                    </a>
                </SidebarMenuButton>
                <SidebarMenuSub>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <a href="#" @click.prevent="showComingSoon('Quantum Computing')" class="transition-all duration-200">
                                Quantum Computing
                            </a>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <a href="#" @click.prevent="showComingSoon('AR Workspace')" class="transition-all duration-200">
                                AR Workspace
                            </a>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <a href="#" @click.prevent="showComingSoon('VR Collaboration')" class="transition-all duration-200">
                                VR Collaboration
                            </a>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <a href="#" @click.prevent="showComingSoon('Brain-Computer Interface')" class="transition-all duration-200">
                                Brain-Computer Interface
                            </a>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                </SidebarMenuSub>
            </SidebarMenuItem>

            <SidebarMenuItem>
                <SidebarMenuButton as-child>
                    <a
                        href="#"
                        class="transition-all duration-200 flex items-center space-x-2 px-2 py-1.5 text-sm rounded-md hover:bg-accent hover:text-accent-foreground"
                        @click.prevent="showComingSoon('Global Features')"
                    >
                        <Globe />
                        <span>Global Features</span>
                    </a>
                </SidebarMenuButton>
                <SidebarMenuSub>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <a href="#" @click.prevent="showComingSoon('Multi-Language')" class="transition-all duration-200">
                                Multi-Language
                            </a>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <a href="#" @click.prevent="showComingSoon('GDPR Compliance')" class="transition-all duration-200">
                                GDPR Compliance
                            </a>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <a href="#" @click.prevent="showComingSoon('Performance Monitoring')" class="transition-all duration-200">
                                Performance Monitoring
                            </a>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                </SidebarMenuSub>
            </SidebarMenuItem>

            <SidebarMenuItem>
                <SidebarMenuButton as-child>
                    <a
                        href="#"
                        class="transition-all duration-200 flex items-center space-x-2 px-2 py-1.5 text-sm rounded-md hover:bg-accent hover:text-accent-foreground"
                        @click.prevent="checkNativeStatus"
                    >
                        <Smartphone />
                        <span>Native App Status</span>
                    </a>
                </SidebarMenuButton>
            </SidebarMenuItem>

            <SidebarMenuItem>
                <SidebarMenuButton as-child>
                    <Link
                        :class="{
                            'text-primary! font-bold': current === 'settings.index'
                        }"
                        :href="route('settings.index')"
                        class="transition-all duration-200"
                        prefetch
                    >
                        <Cog />
                        {{ $t('app.settings') }}
                    </Link>
                </SidebarMenuButton>
                <SidebarMenuSub>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <Link
                                :class="{
                                    'text-primary! font-bold': current === 'settings.general.edit'
                                }"
                                :href="route('settings.general.edit')"
                                class="transition-all duration-200"
                                prefetch
                            >
                                {{ $t('app.general') }}
                            </Link>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                    <SidebarMenuSubItem>
                        <SidebarMenuSubButton as-child>
                            <Link
                                :class="{
                                    'text-primary! font-bold': current === 'settings.start-stop.edit'
                                }"
                                :href="route('settings.start-stop.edit')"
                                class="transition-all duration-200"
                                prefetch
                            >
                                {{ $t('app.auto start/break') }}
                            </Link>
                        </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                </SidebarMenuSub>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>

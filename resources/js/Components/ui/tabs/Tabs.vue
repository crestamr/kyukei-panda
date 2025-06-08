<script setup lang="ts">
import { TabsRoot, type TabsRootProps } from 'radix-vue'
import { computed, type HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

interface TabsProps extends TabsRootProps {
  class?: HTMLAttributes['class']
}

const props = defineProps<TabsProps>()

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props

  return delegated
})
</script>

<template>
  <TabsRoot
    v-bind="delegatedProps"
    :class="cn('', props.class)"
  >
    <slot />
  </TabsRoot>
</template>

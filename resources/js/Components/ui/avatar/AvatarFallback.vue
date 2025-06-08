<script setup lang="ts">
import { AvatarFallback, type AvatarFallbackProps } from 'radix-vue'
import { computed, type HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

interface AvatarFallbackProps extends AvatarFallbackProps {
  class?: HTMLAttributes['class']
}

const props = defineProps<AvatarFallbackProps>()

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props

  return delegated
})
</script>

<template>
  <AvatarFallback
    v-bind="delegatedProps"
    :class="cn(
      'flex h-full w-full items-center justify-center rounded-full bg-muted',
      props.class
    )"
  >
    <slot />
  </AvatarFallback>
</template>

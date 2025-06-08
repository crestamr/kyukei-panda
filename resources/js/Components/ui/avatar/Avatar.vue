<script setup lang="ts">
import { AvatarRoot, type AvatarRootProps } from 'radix-vue'
import { computed, type HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

interface AvatarProps extends AvatarRootProps {
  class?: HTMLAttributes['class']
}

const props = defineProps<AvatarProps>()

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props

  return delegated
})
</script>

<template>
  <AvatarRoot
    v-bind="delegatedProps"
    :class="cn(
      'relative flex h-10 w-10 shrink-0 overflow-hidden rounded-full',
      props.class
    )"
  >
    <slot />
  </AvatarRoot>
</template>

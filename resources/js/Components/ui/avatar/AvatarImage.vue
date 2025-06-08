<script setup lang="ts">
import { AvatarImage, type AvatarImageProps } from 'radix-vue'
import { computed, type HTMLAttributes } from 'vue'
import { cn } from '@/lib/utils'

interface AvatarImageProps extends AvatarImageProps {
  class?: HTMLAttributes['class']
}

const props = defineProps<AvatarImageProps>()

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props

  return delegated
})
</script>

<template>
  <AvatarImage
    v-bind="delegatedProps"
    :class="cn('aspect-square h-full w-full', props.class)"
  />
</template>

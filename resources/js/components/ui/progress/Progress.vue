<script setup lang="ts">
import { cn } from '@/lib/utils'

interface ProgressProps {
  modelValue?: number | null
  class?: any
}

const props = withDefaults(defineProps<ProgressProps>(), {
  modelValue: null,
})
</script>

<template>
  <div
    v-bind="$attrs"
    :class="cn('relative h-2 w-full overflow-hidden rounded-full bg-primary/20', props.class)"
  >
    <div
      class="h-full w-full flex-1 bg-primary transition-all"
      :style="`transform: translateX(-${100 - (props.modelValue ?? 0)}%)`"
    />
  </div>
</template>

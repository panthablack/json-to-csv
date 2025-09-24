<script setup lang="ts">
import { cn } from '@/lib/utils'

interface TextareaProps {
  class?: any
}

const props = withDefaults(defineProps<TextareaProps>(), {})
</script>

<template>
  <textarea
    v-bind="$attrs"
    :class="
      cn(
        'flex min-h-[60px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm placeholder:text-muted-foreground focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50',
        props.class
      )
    "
  />
</template>

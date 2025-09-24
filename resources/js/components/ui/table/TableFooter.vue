<script setup lang="ts">
import { cn } from '@/lib/utils'

interface TableFooterProps {
  class?: any
}

const props = withDefaults(defineProps<TableFooterProps>(), {})
</script>

<template>
  <tfoot
    :class="cn('border-t bg-muted/50 font-medium [&>tr]:last:border-b-0', props.class)"
    v-bind="$attrs"
  >
    <slot />
  </tfoot>
</template>

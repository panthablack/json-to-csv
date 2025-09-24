<script setup lang="ts">
import { cn } from '@/lib/utils';

interface TableCaptionProps {
  class?: any;
}

const props = withDefaults(defineProps<TableCaptionProps>(), {});
</script>

<template>
  <caption
    :class="cn('mt-4 text-sm text-muted-foreground', props.class)"
    v-bind="$attrs"
  >
    <slot />
  </caption>
</template>
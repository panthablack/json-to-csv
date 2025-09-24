<script setup lang="ts">
import { cn } from '@/lib/utils';

interface ScrollBarProps {
  class?: any;
  orientation?: 'vertical' | 'horizontal';
}

const props = withDefaults(defineProps<ScrollBarProps>(), {
  orientation: 'vertical',
});
</script>

<template>
  <div
    v-bind="$attrs"
    :class="cn('flex touch-none select-none transition-colors', props.class)"
  >
    <div class="relative flex-1 rounded-full bg-border" />
  </div>
</template>
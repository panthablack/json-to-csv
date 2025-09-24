<script setup lang="ts">
import { cn } from '@/lib/utils';

interface ScrollAreaProps {
  class?: any;
}

const props = withDefaults(defineProps<ScrollAreaProps>(), {});
</script>

<template>
  <div
    :class="cn('relative overflow-auto', props.class)"
    v-bind="$attrs"
  >
    <slot />
  </div>
</template>
<script setup lang="ts">
import { cn } from '@/lib/utils';

interface TableHeaderProps {
  class?: any;
}

const props = withDefaults(defineProps<TableHeaderProps>(), {});
</script>

<template>
  <thead
    :class="cn('[&_tr]:border-b', props.class)"
    v-bind="$attrs"
  >
    <slot />
  </thead>
</template>
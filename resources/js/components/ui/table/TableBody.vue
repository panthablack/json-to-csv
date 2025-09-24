<script setup lang="ts">
import { cn } from '@/lib/utils';

interface TableBodyProps {
  class?: any;
}

const props = withDefaults(defineProps<TableBodyProps>(), {});
</script>

<template>
  <tbody
    :class="cn('[&_tr:last-child]:border-0', props.class)"
    v-bind="$attrs"
  >
    <slot />
  </tbody>
</template>
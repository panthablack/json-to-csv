<script setup lang="ts">
import { cn } from '@/lib/utils';

interface TableProps {
  class?: any;
}

const props = withDefaults(defineProps<TableProps>(), {});
</script>

<template>
  <div class="relative w-full overflow-auto">
    <table
      :class="cn('w-full caption-bottom text-sm', props.class)"
      v-bind="$attrs"
    >
      <slot />
    </table>
  </div>
</template>
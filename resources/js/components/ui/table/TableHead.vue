<script setup lang="ts">
import { cn } from '@/lib/utils';

interface TableHeadProps {
  class?: any;
}

const props = withDefaults(defineProps<TableHeadProps>(), {});
</script>

<template>
  <th
    :class="cn(
      'h-10 px-2 text-left align-middle font-medium text-muted-foreground [&:has([role=checkbox])]:pr-0 [&>[role=checkbox]]:translate-y-[2px]',
      props.class
    )"
    v-bind="$attrs"
  >
    <slot />
  </th>
</template>
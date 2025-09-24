<script setup lang="ts">
import { AlertCircle } from 'lucide-vue-next'

interface Props {
  message?: string
  show?: boolean
}

withDefaults(defineProps<Props>(), {
  message: '',
  show: false,
})
</script>

<template>
  <Transition
    enter-active-class="transition duration-200 ease-out"
    enter-from-class="transform scale-95 opacity-0"
    enter-to-class="transform scale-100 opacity-100"
    leave-active-class="transition duration-150 ease-in"
    leave-from-class="transform scale-100 opacity-100"
    leave-to-class="transform scale-95 opacity-0"
  >
    <div v-if="show && message" class="mt-1 flex items-center gap-2 text-sm text-destructive">
      <AlertCircle class="h-4 w-4 flex-shrink-0" />
      <span>{{ message }}</span>
    </div>
  </Transition>
</template>

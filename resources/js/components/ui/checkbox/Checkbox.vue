<script setup lang="ts">
import { cn } from '@/lib/utils'
import { Check } from 'lucide-vue-next'
import { CheckboxIndicator, CheckboxRoot } from 'reka-ui'
import { computed, type HTMLAttributes } from 'vue'

interface CheckboxProps {
  modelValue?: boolean
  checked?: boolean
  disabled?: boolean
  required?: boolean
  name?: string
  value?: string
  id?: string
  class?: HTMLAttributes['class']
}

const props = withDefaults(defineProps<CheckboxProps>(), {
  modelValue: false,
  checked: false,
  disabled: false,
  required: false,
})

const emits = defineEmits<{
  'update:modelValue': [value: boolean]
  'update:checked': [checked: boolean]
}>()

const isChecked = computed(() => props.modelValue || props.checked)

const handleCheckedChange = (checked: boolean) => {
  emits('update:modelValue', checked)
  emits('update:checked', checked)
}

const delegatedProps = computed(() => {
  const { class: _, modelValue: __, ...delegated } = props
  return {
    ...delegated,
    checked: isChecked.value,
    onCheckedChange: handleCheckedChange,
  }
})
</script>

<template>
  <CheckboxRoot
    data-slot="checkbox"
    v-bind="delegatedProps"
    :class="
      cn(
        'peer size-4 shrink-0 rounded-[4px] border border-input shadow-xs transition-shadow outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground dark:aria-invalid:ring-destructive/40',
        props.class
      )
    "
  >
    <CheckboxIndicator
      data-slot="checkbox-indicator"
      class="flex items-center justify-center text-current transition-none"
    >
      <slot>
        <Check class="size-3.5" />
      </slot>
    </CheckboxIndicator>
  </CheckboxRoot>
</template>

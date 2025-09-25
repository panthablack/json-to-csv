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

const handleModelValueUpdate = (checked: boolean | 'indeterminate') => {
  const booleanValue = checked === 'indeterminate' ? false : !!checked
  emits('update:modelValue', booleanValue)
  emits('update:checked', booleanValue)
}

const delegatedProps = computed(() => {
  const { class: _, ...delegated } = props
  return {
    ...delegated,
    modelValue: isChecked.value,
  }
})
</script>

<template>
  <CheckboxRoot
    data-slot="checkbox"
    v-bind="delegatedProps"
    @update:model-value="handleModelValueUpdate"
    :class="
      cn(
        'peer size-4 shrink-0 rounded-[4px] border border-input shadow-xs transition-shadow outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 cursor-pointer disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 data-[state=checked]:border-primary data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground dark:aria-invalid:ring-destructive/40',
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

import { describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import Checkbox from './Checkbox.vue'

// Mock reka-ui components
vi.mock('reka-ui', () => ({
  CheckboxRoot: {
    template: `
      <div
        data-testid="checkbox-root"
        :data-checked="modelValue"
        @click="$emit('update:modelValue', !modelValue)"
        role="checkbox"
        :aria-checked="modelValue"
      >
        <slot />
      </div>
    `,
    props: ['modelValue', 'disabled', 'required', 'name', 'value', 'id'],
    emits: ['update:modelValue']
  },
  CheckboxIndicator: {
    template: '<span data-testid="checkbox-indicator"><slot /></span>'
  }
}))

vi.mock('lucide-vue-next', () => ({
  Check: { template: '<svg data-testid="check-icon"></svg>' }
}))

vi.mock('@/lib/utils', () => ({
  cn: (...args: any[]) => args.filter(Boolean).join(' ')
}))

describe('Checkbox Component', () => {
  it('should emit update:modelValue when checkbox is clicked', async () => {
    const wrapper = mount(Checkbox, {
      props: {
        modelValue: false
      }
    })

    const checkboxRoot = wrapper.find('[data-testid="checkbox-root"]')
    expect(checkboxRoot.exists()).toBe(true)

    // Simulate click to check the checkbox
    await checkboxRoot.trigger('click')
    await nextTick()

    // Check that the update:modelValue event was emitted with true
    expect(wrapper.emitted('update:modelValue')).toBeTruthy()
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([true])
  })

  it('should emit update:modelValue when checkbox is unchecked', async () => {
    const wrapper = mount(Checkbox, {
      props: {
        modelValue: true
      }
    })

    const checkboxRoot = wrapper.find('[data-testid="checkbox-root"]')

    // Simulate click to uncheck the checkbox
    await checkboxRoot.trigger('click')
    await nextTick()

    // Check that the update:modelValue event was emitted with false
    expect(wrapper.emitted('update:modelValue')).toBeTruthy()
    expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([false])
  })

  it('should reflect checked state in the DOM', async () => {
    const wrapper = mount(Checkbox, {
      props: {
        modelValue: true
      }
    })

    const checkboxRoot = wrapper.find('[data-testid="checkbox-root"]')
    expect(checkboxRoot.attributes('data-checked')).toBe('true')
    expect(checkboxRoot.attributes('aria-checked')).toBe('true')
  })

  it('should reflect unchecked state in the DOM', async () => {
    const wrapper = mount(Checkbox, {
      props: {
        modelValue: false
      }
    })

    const checkboxRoot = wrapper.find('[data-testid="checkbox-root"]')
    expect(checkboxRoot.attributes('data-checked')).toBe('false')
    expect(checkboxRoot.attributes('aria-checked')).toBe('false')
  })

  it('should update when modelValue prop changes', async () => {
    const wrapper = mount(Checkbox, {
      props: {
        modelValue: false
      }
    })

    let checkboxRoot = wrapper.find('[data-testid="checkbox-root"]')
    expect(checkboxRoot.attributes('data-checked')).toBe('false')

    // Change the modelValue prop
    await wrapper.setProps({ modelValue: true })
    await nextTick()

    checkboxRoot = wrapper.find('[data-testid="checkbox-root"]')
    expect(checkboxRoot.attributes('data-checked')).toBe('true')
  })

  it('should handle both modelValue and checked props correctly', async () => {
    // Test that modelValue takes precedence
    const wrapper = mount(Checkbox, {
      props: {
        modelValue: true,
        checked: false
      }
    })

    const checkboxRoot = wrapper.find('[data-testid="checkbox-root"]')
    expect(checkboxRoot.attributes('data-checked')).toBe('true')
  })

  it('should use checked prop when no modelValue is provided', async () => {
    const wrapper = mount(Checkbox, {
      props: {
        checked: true
      }
    })

    const checkboxRoot = wrapper.find('[data-testid="checkbox-root"]')
    expect(checkboxRoot.attributes('data-checked')).toBe('true')
  })
})
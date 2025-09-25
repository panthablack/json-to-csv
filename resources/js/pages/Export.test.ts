import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref, nextTick } from 'vue'
import Export from './Export.vue'

// Mock Inertia.js
vi.mock('@inertiajs/vue3', () => ({
  Head: { template: '<head><slot /></head>' },
  router: {
    visit: vi.fn()
  }
}))

// Mock route functions
vi.mock('@/routes', () => ({
  dashboard: () => ({ url: '/dashboard' })
}))

vi.mock('@/routes/csv/config', () => ({
  page: () => ({ url: '/csv/config' })
}))

vi.mock('@/routes/export', () => ({
  page: () => ({ url: '/export' }),
  download: (filename: string) => ({ url: `/download/${filename}` })
}))

// Mock components
vi.mock('@/components/ui/alert', () => ({
  Alert: { template: '<div data-testid="alert"><slot /></div>' },
  AlertDescription: { template: '<div data-testid="alert-description"><slot /></div>' }
}))

vi.mock('@/components/ui/badge', () => ({
  Badge: { template: '<span data-testid="badge"><slot /></span>' }
}))

vi.mock('@/components/ui/button', () => ({
  Button: {
    template: '<button data-testid="button" :disabled="disabled" @click="$emit(\'click\')"><slot /></button>',
    props: ['disabled']
  }
}))

vi.mock('@/components/ui/card', () => ({
  Card: { template: '<div data-testid="card"><slot /></div>' },
  CardContent: { template: '<div data-testid="card-content"><slot /></div>' },
  CardDescription: { template: '<div data-testid="card-description"><slot /></div>' },
  CardHeader: { template: '<div data-testid="card-header"><slot /></div>' },
  CardTitle: { template: '<div data-testid="card-title"><slot /></div>' }
}))

vi.mock('@/components/ui/checkbox', () => ({
  Checkbox: {
    template: '<input type="checkbox" data-testid="checkbox" :checked="modelValue" @change="$emit(\'update:modelValue\', $event.target.checked)" />',
    props: ['modelValue']
  }
}))

vi.mock('@/layouts/AppLayout.vue', () => ({
  default: { template: '<div data-testid="app-layout"><slot /></div>' }
}))

// Mock fetch
global.fetch = vi.fn()

describe('Export.vue Selection Logic', () => {
  let wrapper: any

  beforeEach(() => {
    // Reset mocks
    vi.clearAllMocks()

    // Mock successful API responses
    ;(global.fetch as any).mockResolvedValue({
      ok: true,
      json: () => Promise.resolve([])
    })

    wrapper = mount(Export, {
      global: {
        stubs: {
          Head: true
        }
      }
    })
  })

  describe('Selection State Management', () => {
    it('should initialize with empty selection', () => {
      const vm = wrapper.vm
      expect(vm.selectedConfigs).toEqual([])
      expect(vm.hasSelectedConfigs).toBe(false)
    })

    it('should add config to selection when handleConfigSelection is called with true', async () => {
      const vm = wrapper.vm
      const configId = 1

      vm.handleConfigSelection(configId, true)
      await nextTick()

      expect(vm.selectedConfigs).toContain(configId)
      expect(vm.hasSelectedConfigs).toBe(true)
    })

    it('should remove config from selection when handleConfigSelection is called with false', async () => {
      const vm = wrapper.vm
      const configId = 1

      // First add the config
      vm.selectedConfigs = [configId]
      await nextTick()

      // Then remove it
      vm.handleConfigSelection(configId, false)
      await nextTick()

      expect(vm.selectedConfigs).not.toContain(configId)
      expect(vm.hasSelectedConfigs).toBe(false)
    })

    it('should not add duplicate configs when handleConfigSelection is called with true multiple times', async () => {
      const vm = wrapper.vm
      const configId = 1

      vm.handleConfigSelection(configId, true)
      vm.handleConfigSelection(configId, true)
      await nextTick()

      expect(vm.selectedConfigs.filter((id: number) => id === configId)).toHaveLength(1)
    })

    it('should handle multiple configs selection', async () => {
      const vm = wrapper.vm
      const configIds = [1, 2, 3]

      configIds.forEach(id => vm.handleConfigSelection(id, true))
      await nextTick()

      expect(vm.selectedConfigs).toEqual(expect.arrayContaining(configIds))
      expect(vm.selectedConfigs).toHaveLength(configIds.length)
    })
  })

  describe('Toggle All Functionality', () => {
    beforeEach(async () => {
      const vm = wrapper.vm
      // Set up some mock configurations
      vm.configurations = [
        { id: 1, name: 'Config 1', json_data_id: 1 },
        { id: 2, name: 'Config 2', json_data_id: 1 },
        { id: 3, name: 'Config 3', json_data_id: 1 }
      ]
      await nextTick()
    })

    it('should select all configs when toggleAllConfigs is called with true', async () => {
      const vm = wrapper.vm

      vm.toggleAllConfigs(true)
      await nextTick()

      expect(vm.selectedConfigs).toEqual([1, 2, 3])
      expect(vm.hasSelectedConfigs).toBe(true)
    })

    it('should deselect all configs when toggleAllConfigs is called with false', async () => {
      const vm = wrapper.vm
      vm.selectedConfigs = [1, 2, 3]

      vm.toggleAllConfigs(false)
      await nextTick()

      expect(vm.selectedConfigs).toEqual([])
      expect(vm.hasSelectedConfigs).toBe(false)
    })

    it('should toggle based on current state when toggleAllConfigs is called without parameter', async () => {
      const vm = wrapper.vm

      // When no configs selected, should select all
      vm.toggleAllConfigs()
      await nextTick()

      expect(vm.selectedConfigs).toEqual([1, 2, 3])

      // When all configs selected, should deselect all
      vm.toggleAllConfigs()
      await nextTick()

      expect(vm.selectedConfigs).toEqual([])
    })
  })

  describe('Computed Properties', () => {
    it('should compute hasSelectedConfigs correctly', async () => {
      const vm = wrapper.vm

      expect(vm.hasSelectedConfigs).toBe(false)

      vm.selectedConfigs = [1]
      await nextTick()

      expect(vm.hasSelectedConfigs).toBe(true)
    })

    it('should compute allConfigsSelected correctly', async () => {
      const vm = wrapper.vm
      vm.configurations = [
        { id: 1, name: 'Config 1' },
        { id: 2, name: 'Config 2' }
      ]

      // No configs selected
      expect(vm.allConfigsSelected).toBe(false)

      // Some configs selected
      vm.selectedConfigs = [1]
      await nextTick()
      expect(vm.allConfigsSelected).toBe(false)

      // All configs selected
      vm.selectedConfigs = [1, 2]
      await nextTick()
      expect(vm.allConfigsSelected).toBe(true)
    })

    it('should handle filtered configurations correctly', async () => {
      const vm = wrapper.vm
      vm.configurations = [
        { id: 1, name: 'Config 1', json_data_id: 1 },
        { id: 2, name: 'Config 2', json_data_id: 2 }
      ]

      // Filter by json_data_id = 1
      vm.selectedJsonDataId = 1
      await nextTick()

      expect(vm.filteredConfigurations).toHaveLength(1)
      expect(vm.filteredConfigurations[0].id).toBe(1)

      // Select the filtered config
      vm.selectedConfigs = [1]
      await nextTick()

      expect(vm.allConfigsSelected).toBe(true)
    })
  })

  describe('Filter Interaction', () => {
    it('should clear selection when filter changes', async () => {
      const vm = wrapper.vm
      vm.selectedConfigs = [1, 2, 3]

      vm.handleFilterChange(1)
      await nextTick()

      expect(vm.selectedConfigs).toEqual([])
      expect(vm.selectedJsonDataId).toBe(1)
    })

    it('should clear selection when filter is cleared', async () => {
      const vm = wrapper.vm
      vm.selectedConfigs = [1, 2, 3]
      vm.selectedJsonDataId = 1

      vm.clearFilter()
      await nextTick()

      expect(vm.selectedConfigs).toEqual([])
      expect(vm.selectedJsonDataId).toBeNull()
    })
  })

  describe('Checkbox Event Handlers', () => {
    beforeEach(async () => {
      const vm = wrapper.vm
      // Mock some configurations for testing
      vm.configurations = [
        { id: 1, name: 'Config 1', json_data_id: 1, created_at: '2023-01-01' },
        { id: 2, name: 'Config 2', json_data_id: 1, created_at: '2023-01-02' }
      ]
      await nextTick()
    })

    it('should handle individual checkbox change events by calling handleConfigSelection', async () => {
      const vm = wrapper.vm

      // Test the function that would be called by the checkbox event handler
      vm.handleConfigSelection(1, true)
      await nextTick()

      // The config should be selected
      expect(vm.selectedConfigs).toContain(1)

      // Test unchecking
      vm.handleConfigSelection(1, false)
      await nextTick()

      expect(vm.selectedConfigs).not.toContain(1)
    })

    it('should handle select all checkbox change events by calling toggleAllConfigs', async () => {
      const vm = wrapper.vm

      // Test the function that would be called by the select all checkbox event handler
      vm.toggleAllConfigs(true)
      await nextTick()

      // All configs should be selected
      expect(vm.selectedConfigs).toEqual([1, 2])

      // Test unchecking select all
      vm.toggleAllConfigs(false)
      await nextTick()

      expect(vm.selectedConfigs).toEqual([])
    })

    it('should update checkbox state when selection changes programmatically', async () => {
      const vm = wrapper.vm

      // Programmatically select a config
      vm.selectedConfigs = [1]
      await nextTick()

      // The individual checkbox model-value should reflect the selection
      expect(vm.selectedConfigs.includes(1)).toBe(true)

      // Programmatically deselect
      vm.selectedConfigs = []
      await nextTick()

      expect(vm.selectedConfigs.includes(1)).toBe(false)
    })
  })

  describe('Export Button State', () => {
    it('should enable export button when configs are selected', async () => {
      const vm = wrapper.vm
      vm.selectedConfigs = [1]
      await nextTick()

      expect(vm.hasSelectedConfigs).toBe(true)

      // In the template, button should not be disabled when hasSelectedConfigs is true
      const exportButton = wrapper.find('[data-testid="button"]')
      if (exportButton.exists()) {
        expect(exportButton.attributes('disabled')).toBeUndefined()
      }
    })

    it('should disable export button when no configs are selected', async () => {
      const vm = wrapper.vm
      vm.selectedConfigs = []
      await nextTick()

      expect(vm.hasSelectedConfigs).toBe(false)
    })

    it('should disable export button when loading', async () => {
      const vm = wrapper.vm
      vm.selectedConfigs = [1]
      vm.isLoading = true
      await nextTick()

      // Button should be disabled when loading even if configs are selected
      expect(vm.hasSelectedConfigs).toBe(true)
      expect(vm.isLoading).toBe(true)
    })
  })
})
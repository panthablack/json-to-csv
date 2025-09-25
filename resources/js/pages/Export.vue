<script setup lang="ts">
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Checkbox } from '@/components/ui/checkbox'
import AppLayout from '@/layouts/AppLayout.vue'
import { dashboard } from '@/routes'
import { page as csvConfigPage } from '@/routes/csv/config'
import { page as exportPage } from '@/routes/export'
import { type BreadcrumbItem } from '@/types'
import { Head, router } from '@inertiajs/vue3'
import {
  AlertCircle,
  Archive,
  Copy,
  Download,
  Eye,
  FileText,
  Filter,
  Settings,
  Trash2,
  X,
} from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: dashboard().url },
  { title: 'Export', href: exportPage().url },
]

const props = defineProps<{
  configuration_id?: number
}>()

// State
const configurations = ref<any[]>([])
const selectedConfigs = ref<number[]>([])
const jsonFiles = ref<any[]>([])
const selectedJsonDataId = ref<number | null>(null)
const isLoading = ref(false)
const error = ref<string | null>(null)

const hasSelectedConfigs = computed(() => selectedConfigs.value.length > 0)
const filteredConfigurations = computed(() => {
  if (!selectedJsonDataId.value) return configurations.value
  return configurations.value.filter(config => config.json_data_id === selectedJsonDataId.value)
})
const allConfigsSelected = computed(
  () =>
    filteredConfigurations.value.length > 0 &&
    filteredConfigurations.value.every(config => selectedConfigs.value.includes(config.id))
)

async function loadConfigurations() {
  try {
    isLoading.value = true
    const url = selectedJsonDataId.value
      ? `/api/csv-config?json_data_id=${selectedJsonDataId.value}`
      : '/api/csv-config'

    const response = await fetch(url)
    if (!response.ok) throw new Error('Failed to load configurations')

    configurations.value = await response.json()

    // Auto-select if configuration_id provided
    if (props.configuration_id) {
      selectedConfigs.value = [props.configuration_id]
    }
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to load configurations'
  } finally {
    isLoading.value = false
  }
}


async function loadJsonFiles() {
  try {
    const response = await fetch('/api/json')
    if (!response.ok) throw new Error('Failed to load JSON files')

    jsonFiles.value = await response.json()
  } catch (err) {
    console.error('Failed to load JSON files:', err)
  }
}

function handleFilterChange(value: number | null) {
  selectedJsonDataId.value = value
  selectedConfigs.value = [] // Clear selection when filter changes
  loadConfigurations()
}

function clearFilter() {
  selectedJsonDataId.value = null
  selectedConfigs.value = []
  loadConfigurations()
}

function handleConfigSelection(configId: number, checked: boolean) {
  const index = selectedConfigs.value.indexOf(configId)

  if (checked && index === -1) {
    // Add to selection if checked and not already selected
    selectedConfigs.value.push(configId)
  } else if (!checked && index > -1) {
    // Remove from selection if unchecked and currently selected
    selectedConfigs.value.splice(index, 1)
  }
}

function toggleAllConfigs(checked?: boolean) {
  // Use the provided checked value if available, otherwise toggle based on current state
  const shouldSelectAll = checked !== undefined ? checked : !allConfigsSelected.value

  if (shouldSelectAll) {
    selectedConfigs.value = filteredConfigurations.value.map(config => config.id)
  } else {
    selectedConfigs.value = []
  }
}

async function exportSingle(configId: number) {
  try {
    const response = await fetch(`/api/export/single/${configId}`)
    if (!response.ok) throw new Error('Export failed')

    // Extract filename from Content-Disposition header
    const contentDisposition = response.headers.get('content-disposition')
    let filename = `export_${Date.now()}.csv` // fallback

    if (contentDisposition) {
      const matches = contentDisposition.match(/filename="?([^"]+)"?/)
      if (matches && matches[1]) {
        filename = matches[1]
      }
    }

    // Trigger download
    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = filename
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    window.URL.revokeObjectURL(url)

  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Export failed'
  }
}

async function exportMultiple() {
  if (!hasSelectedConfigs.value) return

  // If only one config selected, use single export instead
  if (selectedConfigs.value.length === 1) {
    await exportSingle(selectedConfigs.value[0])
    selectedConfigs.value = []
    return
  }

  try {
    isLoading.value = true
    const response = await fetch('/api/export/multiple', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN':
          document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
      body: JSON.stringify({
        configuration_ids: selectedConfigs.value,
      }),
    })

    if (!response.ok) throw new Error('Bulk export failed')

    // Trigger download
    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `bulk_export_${Date.now()}.zip`
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    window.URL.revokeObjectURL(url)

    selectedConfigs.value = []
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Bulk export failed'
  } finally {
    isLoading.value = false
  }
}

async function duplicateConfiguration(configId: number) {
  try {
    const response = await fetch(`/api/csv-config/${configId}/duplicate`, {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN':
          document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    })

    if (!response.ok) throw new Error('Failed to duplicate configuration')

    await loadConfigurations()
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to duplicate configuration'
  }
}

async function deleteConfiguration(configId: number) {
  if (!confirm('Are you sure you want to delete this configuration?')) return

  try {
    const response = await fetch(`/api/csv-config/${configId}`, {
      method: 'DELETE',
      headers: {
        'X-CSRF-TOKEN':
          document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    })

    if (!response.ok) throw new Error('Failed to delete configuration')

    await loadConfigurations()
    selectedConfigs.value = selectedConfigs.value.filter(id => id !== configId)
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to delete configuration'
  }
}


onMounted(() => {
  loadJsonFiles()
  loadConfigurations()
})
</script>

<template>
  <Head title="Export" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 p-6">
      <div class="flex flex-col gap-2">
        <h1 class="text-2xl font-bold">Export Manager</h1>
        <p class="text-muted-foreground">Export your configurations and manage downloads</p>
      </div>

      <div class="space-y-6">
        <!-- Configurations -->
        <div>
          <Card class="card-gradient border-2">
            <CardHeader>
              <div class="flex items-center justify-between">
                <div>
                  <CardTitle class="flex items-center gap-2">
                    <Settings class="h-5 w-5" />
                    CSV Configurations
                  </CardTitle>
                  <CardDescription> Select configurations to export </CardDescription>
                </div>
                <Button size="sm" variant="outline" class="btn-primary-gradient text-primary-foreground border-0" @click="router.visit(csvConfigPage().url)">
                  New Configuration
                </Button>
              </div>

              <!-- Filter UI -->
              <div class="mt-4 flex items-center gap-3">
                <div class="flex items-center gap-2">
                  <Filter class="h-4 w-4 text-muted-foreground" />
                  <span class="text-sm text-muted-foreground">Filter by JSON:</span>
                </div>
                <div class="flex items-center gap-2">
                  <select
                    :value="selectedJsonDataId || ''"
                    @change="
                      e =>
                        handleFilterChange(
                          (e.target as HTMLInputElement)?.value
                            ? Number((e.target as HTMLInputElement)?.value)
                            : null
                        )
                    "
                    class="rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none"
                  >
                    <option value="">All JSON files</option>
                    <option v-for="file in jsonFiles" :key="file.id" :value="file.id">
                      {{ file.name }}
                    </option>
                  </select>
                  <Button v-if="selectedJsonDataId" size="sm" variant="ghost" @click="clearFilter">
                    <X class="h-4 w-4" />
                  </Button>
                </div>
              </div>
            </CardHeader>
            <CardContent>
              <div v-if="filteredConfigurations.length === 0" class="py-8 text-center">
                <FileText class="mx-auto mb-4 h-16 w-16 text-muted-foreground" />
                <p class="mb-2 text-lg font-medium">
                  {{
                    selectedJsonDataId
                      ? 'No configurations found for this JSON file'
                      : 'No configurations found'
                  }}
                </p>
                <p class="mb-4 text-muted-foreground">
                  {{
                    selectedJsonDataId
                      ? 'Try selecting a different JSON file or create a new configuration'
                      : 'Create a CSV configuration first'
                  }}
                </p>
                <Button @click="router.visit(csvConfigPage().url)"> Create Configuration </Button>
              </div>

              <div v-else class="space-y-4">
                <!-- Bulk Actions -->
                <div class="flex items-center justify-between border-b pb-4">
                  <div class="flex items-center gap-3">
                    <Checkbox
                      :model-value="allConfigsSelected"
                      @update:model-value="toggleAllConfigs"
                    />
                    <span class="text-sm text-muted-foreground">
                      {{ selectedConfigs.length }} of {{ filteredConfigurations.length }} selected
                    </span>
                  </div>
                  <div class="flex items-center gap-2">
                    <Button
                      size="sm"
                      class="btn-primary-gradient text-primary-foreground border-0"
                      @click="exportMultiple"
                      :disabled="!hasSelectedConfigs || isLoading"
                    >
                      <Download class="mr-2 h-4 w-4" />
                      Export Selected ({{ selectedConfigs.length }})
                    </Button>
                  </div>
                </div>

                <!-- Configuration List -->
                <div class="space-y-3">
                  <div
                    v-for="config in filteredConfigurations"
                    :key="config.id"
                    class="rounded-lg border p-4 transition-all duration-200 hover:bg-accent/30 hover:shadow-md hover:border-primary/30 glass"
                  >
                    <div class="flex items-start gap-3">
                      <Checkbox
                        :model-value="selectedConfigs.includes(config.id)"
                        @update:model-value="checked => handleConfigSelection(config.id, checked)"
                      />
                      <div class="min-w-0 flex-1">
                        <div class="mb-2 flex items-center justify-between">
                          <h3 class="truncate font-medium">{{ config.name }}</h3>
                          <Badge variant="secondary" class="text-xs">
                            {{ config.json_data?.name || 'Unknown' }}
                          </Badge>
                        </div>
                        <p
                          v-if="config.description"
                          class="mb-3 line-clamp-2 text-sm text-muted-foreground"
                        >
                          {{ config.description }}
                        </p>
                        <div class="flex items-center justify-between">
                          <span class="text-xs text-muted-foreground">
                            Created {{ new Date(config.created_at).toLocaleDateString() }}
                          </span>
                          <div class="flex items-center gap-1">
                            <Button
                              size="sm"
                              variant="ghost"
                              @click="
                                router.visit(csvConfigPage().url, {
                                  data: { config_id: config.id },
                                })
                              "
                            >
                              <Eye class="h-4 w-4" />
                            </Button>
                            <Button
                              size="sm"
                              variant="ghost"
                              @click="duplicateConfiguration(config.id)"
                            >
                              <Copy class="h-4 w-4" />
                            </Button>
                            <Button size="sm" variant="ghost" @click="exportSingle(config.id)">
                              <Download class="h-4 w-4" />
                            </Button>
                            <Button
                              size="sm"
                              variant="ghost"
                              @click="deleteConfiguration(config.id)"
                            >
                              <Trash2 class="h-4 w-4" />
                            </Button>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Error Message -->
          <Alert v-if="error" variant="destructive">
            <AlertCircle class="h-4 w-4" />
            <AlertDescription>{{ error }}</AlertDescription>
          </Alert>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

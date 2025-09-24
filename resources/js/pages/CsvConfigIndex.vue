<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Alert, AlertDescription } from '@/components/ui/alert'
import AppLayout from '@/layouts/AppLayout.vue'
import { apiGet, apiPost, apiDelete } from '@/composables/useApi'
import { dashboard } from '@/routes'
import { page as exportPage } from '@/routes/export'
import { type BreadcrumbItem } from '@/types'
import { Head, router } from '@inertiajs/vue3'
import {
  AlertCircle,
  FileText,
  Plus,
  Settings,
  Trash2,
  Copy,
  Download,
  Eye
} from 'lucide-vue-next'
import { computed, onMounted, ref } from 'vue'

const props = defineProps<{
  json_data_id: number
}>()

const breadcrumbs: BreadcrumbItem[] = [
  { title: 'Dashboard', href: dashboard().url },
  { title: 'CSV Configurations', href: '#' },
]

// State
const isLoading = ref(false)
const error = ref<string | null>(null)
const jsonData = ref<any>(null)
const configurations = ref<any[]>([])
const isLoadingConfigs = ref(false)

const hasConfigurations = computed(() => configurations.value.length > 0)

async function loadJsonData() {
  try {
    isLoading.value = true
    const response = await apiGet(`/api/json/${props.json_data_id}`)
    if (!response.ok) throw new Error('Failed to load JSON data')

    jsonData.value = await response.json()
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to load JSON data'
  } finally {
    isLoading.value = false
  }
}

async function loadConfigurations() {
  try {
    isLoadingConfigs.value = true
    const response = await apiGet(`/api/json-data/${props.json_data_id}/csv-config`)
    if (!response.ok) throw new Error('Failed to load configurations')

    configurations.value = await response.json()
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to load configurations'
  } finally {
    isLoadingConfigs.value = false
  }
}

async function deleteConfiguration(configId: number) {
  if (!confirm('Are you sure you want to delete this configuration?')) return

  try {
    const response = await apiDelete(`/api/csv-config/${configId}`)
    if (!response.ok) throw new Error('Failed to delete configuration')

    // Remove from local state
    configurations.value = configurations.value.filter(config => config.id !== configId)
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to delete configuration'
  }
}

async function duplicateConfiguration(configId: number) {
  try {
    const response = await apiPost(`/api/csv-config/${configId}/duplicate`, {})
    if (!response.ok) throw new Error('Failed to duplicate configuration')

    // Reload configurations to show the new copy
    await loadConfigurations()
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to duplicate configuration'
  }
}

function createNewConfiguration() {
  router.visit(`/json-data/${props.json_data_id}/create-csv-config`)
}

function editConfiguration(configId: number) {
  // For now, we'll navigate to the export page with the configuration
  // In the future, this could navigate to an edit page
  router.visit(exportPage().url, {
    data: { configuration_id: configId },
  })
}

function exportConfiguration(configId: number) {
  router.visit(exportPage().url, {
    data: { configuration_id: configId },
  })
}

onMounted(() => {
  loadJsonData()
  loadConfigurations()
})
</script>

<template>
  <Head title="CSV Configurations" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 p-6">
      <div class="flex flex-col gap-2">
        <h1 class="text-2xl font-bold">CSV Configurations</h1>
        <p class="text-muted-foreground">
          Manage CSV export configurations for {{ jsonData?.original_filename || 'your JSON data' }}
        </p>
      </div>

      <!-- Error Message -->
      <Alert v-if="error" variant="destructive">
        <AlertCircle class="h-4 w-4" />
        <AlertDescription>{{ error }}</AlertDescription>
      </Alert>

      <!-- Source JSON Info -->
      <Card v-if="jsonData && !isLoading">
        <CardHeader>
          <CardTitle class="flex items-center gap-2">
            <FileText class="h-5 w-5" />
            Source Data
          </CardTitle>
        </CardHeader>
        <CardContent class="grid grid-cols-1 md:grid-cols-4 gap-4 text-sm">
          <div class="flex justify-between">
            <span class="text-muted-foreground">Filename:</span>
            <span class="font-medium">{{ jsonData.original_filename }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-muted-foreground">Records:</span>
            <span class="font-medium">{{ jsonData.record_count?.toLocaleString() || 'N/A' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="text-muted-foreground">Status:</span>
            <Badge :variant="jsonData.status === 'processed' ? 'secondary' : 'destructive'">
              {{ jsonData.status }}
            </Badge>
          </div>
          <div class="flex justify-between">
            <span class="text-muted-foreground">Uploaded:</span>
            <span class="font-medium">{{ new Date(jsonData.created_at).toLocaleDateString() }}</span>
          </div>
        </CardContent>
      </Card>

      <!-- Configurations Section -->
      <div class="space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-xl font-semibold">Export Configurations</h2>
            <p class="text-muted-foreground">Saved CSV export configurations for this JSON data</p>
          </div>
          <Button @click="createNewConfiguration">
            <Plus class="mr-2 h-4 w-4" />
            New Configuration
          </Button>
        </div>

        <!-- Loading State -->
        <div v-if="isLoadingConfigs" class="py-12 text-center">
          <p class="text-lg font-medium">Loading configurations...</p>
        </div>

        <!-- Empty State -->
        <div v-else-if="!hasConfigurations" class="py-12 text-center">
          <Settings class="mx-auto mb-4 h-16 w-16 text-muted-foreground" />
          <p class="mb-2 text-lg font-medium">No configurations found</p>
          <p class="mb-4 text-muted-foreground">Create your first CSV export configuration</p>
          <Button @click="createNewConfiguration">
            <Plus class="mr-2 h-4 w-4" />
            Create Configuration
          </Button>
        </div>

        <!-- Configurations Table -->
        <Card v-else>
          <CardContent class="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Description</TableHead>
                  <TableHead>Field Mappings</TableHead>
                  <TableHead>Created</TableHead>
                  <TableHead class="w-32">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                <TableRow
                  v-for="config in configurations"
                  :key="config.id"
                  class="cursor-pointer transition-colors hover:bg-muted/50"
                  @click="editConfiguration(config.id)"
                >
                  <TableCell class="font-medium">{{ config.name }}</TableCell>
                  <TableCell class="max-w-xs truncate text-muted-foreground">
                    {{ config.description || 'No description' }}
                  </TableCell>
                  <TableCell>
                    <Badge variant="outline">
                      {{ Object.keys(config.field_mappings || {}).length }} fields
                    </Badge>
                  </TableCell>
                  <TableCell class="text-muted-foreground">
                    {{ new Date(config.created_at).toLocaleDateString() }}
                  </TableCell>
                  <TableCell @click.stop>
                    <div class="flex items-center gap-1">
                      <Button
                        size="sm"
                        variant="ghost"
                        @click="exportConfiguration(config.id)"
                        title="Export CSV"
                      >
                        <Download class="h-4 w-4" />
                      </Button>
                      <Button
                        size="sm"
                        variant="ghost"
                        @click="duplicateConfiguration(config.id)"
                        title="Duplicate Configuration"
                      >
                        <Copy class="h-4 w-4" />
                      </Button>
                      <Button
                        size="sm"
                        variant="ghost"
                        @click="deleteConfiguration(config.id)"
                        title="Delete Configuration"
                        class="text-destructive hover:text-destructive"
                      >
                        <Trash2 class="h-4 w-4" />
                      </Button>
                    </div>
                  </TableCell>
                </TableRow>
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>
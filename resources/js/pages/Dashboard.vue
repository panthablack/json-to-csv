<script setup lang="ts">
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { apiDelete } from '@/composables/useApi'
import AppLayout from '@/layouts/AppLayout.vue'
import { dashboard } from '@/routes'
import { index as jsonDataCsvConfig } from '@/routes/csv/config'
import { type BreadcrumbItem } from '@/types'
import { Head, router } from '@inertiajs/vue3'
import { Download, FileText, Settings, Trash2, TrendingUp, Upload } from 'lucide-vue-next'
import { onMounted, ref } from 'vue'

const breadcrumbs: BreadcrumbItem[] = [
  {
    title: 'Dashboard',
    href: dashboard().url,
  },
]

const stats = ref({
  jsonFiles: 0,
  configurations: 0,
  exports: 0,
})

const recentFiles = ref<any[]>([])
const recentConfigs = ref<any[]>([])

async function loadDashboardData() {
  try {
    // Load JSON files
    const jsonResponse = await fetch('/api/json')
    if (jsonResponse.ok) {
      const jsonFiles = await jsonResponse.json()
      stats.value.jsonFiles = jsonFiles.length
      recentFiles.value = jsonFiles.slice(0, 5)
    }

    // Load configurations
    const configResponse = await fetch('/api/csv-config')
    if (configResponse.ok) {
      const configs = await configResponse.json()
      stats.value.configurations = configs.length
      recentConfigs.value = configs.slice(0, 5)
    }

    // Load exports
    const exportResponse = await fetch('/api/export/list')
    if (exportResponse.ok) {
      const exports = await exportResponse.json()
      stats.value.exports = exports.length
    }
  } catch (error) {
    console.error('Failed to load dashboard data:', error)
  }
}

onMounted(() => {
  loadDashboardData()
})

async function deleteJsonFile(fileId: number) {
  if (!confirm('Are you sure you want to delete this JSON file? This action cannot be undone.')) {
    return
  }

  try {
    const response = await apiDelete(`/api/json/${fileId}`)

    if (!response.ok) {
      const errorText = await response.text()
      throw new Error(`Failed to delete JSON file (${response.status}): ${errorText}`)
    }

    // Reload dashboard data to reflect the deletion
    await loadDashboardData()
  } catch (error) {
    console.error('Failed to delete JSON file:', error)
    alert('Failed to delete JSON file. Please try again.')
  }
}
</script>

<template>
  <Head title="Dashboard" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 p-6">
      <div class="flex flex-col gap-2">
        <h1 class="text-2xl font-bold">JSON to CSV Dashboard</h1>
        <p class="text-muted-foreground">Transform JSON data into customizable CSV exports</p>
      </div>

      <!-- Stats Cards -->
      <div class="grid auto-rows-min gap-4 md:grid-cols-3">
        <Card class="transition-shadow hover:shadow-md">
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">JSON Files</CardTitle>
            <FileText class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ stats.jsonFiles }}</div>
            <p class="text-xs text-muted-foreground">Uploaded data sources</p>
          </CardContent>
        </Card>

        <Card class="transition-shadow hover:shadow-md">
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">Configurations</CardTitle>
            <Settings class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ stats.configurations }}</div>
            <p class="text-xs text-muted-foreground">CSV export configurations</p>
          </CardContent>
        </Card>

        <Card class="transition-shadow hover:shadow-md">
          <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
            <CardTitle class="text-sm font-medium">Exports</CardTitle>
            <Download class="h-4 w-4 text-muted-foreground" />
          </CardHeader>
          <CardContent>
            <div class="text-2xl font-bold">{{ stats.exports }}</div>
            <p class="text-xs text-muted-foreground">Generated CSV files</p>
          </CardContent>
        </Card>
      </div>

      <!-- Quick Actions -->
      <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
        <Button
          class="flex h-auto flex-col items-center gap-2 p-4"
          @click="router.visit('/json-upload')"
        >
          <Upload class="h-6 w-6" />
          <span>Upload JSON</span>
        </Button>
        <Button
          variant="outline"
          class="flex h-auto flex-col items-center gap-2 p-4"
          @click="router.visit('/csv-config')"
        >
          <Settings class="h-6 w-6" />
          <span>Configure CSV</span>
        </Button>
        <Button
          variant="outline"
          class="flex h-auto flex-col items-center gap-2 p-4"
          @click="router.visit('/export')"
        >
          <Download class="h-6 w-6" />
          <span>Export & Download</span>
        </Button>
        <Button variant="outline" class="flex h-auto flex-col items-center gap-2 p-4" disabled>
          <TrendingUp class="h-6 w-6" />
          <span>Analytics</span>
        </Button>
      </div>

      <!-- JSON Files Management -->
      <Card>
        <CardHeader>
          <CardTitle class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <FileText class="h-5 w-5" />
              All JSON Files
            </div>
            <Button size="sm" @click="router.visit('/json-upload')">
              <Upload class="mr-2 h-4 w-4" />
              Upload New
            </Button>
          </CardTitle>
          <CardDescription> Manage your uploaded JSON data files </CardDescription>
        </CardHeader>
        <CardContent>
          <div v-if="recentFiles.length === 0" class="py-8 text-center">
            <FileText class="mx-auto mb-2 h-8 w-8 text-muted-foreground" />
            <p class="text-sm text-muted-foreground">No JSON files uploaded yet</p>
            <Button size="sm" class="mt-2" @click="router.visit('/json-upload')">
              Upload First File
            </Button>
          </div>

          <div v-else class="space-y-3">
            <div
              v-for="file in recentFiles"
              :key="file.id"
              class="rounded-lg border p-4 transition-colors hover:bg-muted/50"
            >
              <div class="flex items-center justify-between">
                <div class="min-w-0 flex-1">
                  <h4 class="truncate text-sm font-medium">
                    {{ file.original_filename }}
                  </h4>
                  <p class="mt-1 text-xs text-muted-foreground">
                    {{ file.record_count }} records • Uploaded
                    {{ new Date(file.created_at).toLocaleDateString() }}
                  </p>
                </div>
                <div class="flex items-center gap-2">
                  <Badge
                    :variant="file.status === 'processed' ? 'secondary' : 'destructive'"
                    class="text-xs"
                  >
                    {{ file.status }}
                  </Badge>
                  <Button
                    size="sm"
                    variant="outline"
                    @click="router.visit(jsonDataCsvConfig(file.id).url)"
                  >
                    <Settings class="mr-2 h-4 w-4" />
                    Configure
                  </Button>
                  <Button
                    size="sm"
                    variant="outline"
                    class="text-destructive hover:text-destructive"
                    @click="deleteJsonFile(file.id)"
                  >
                    <Trash2 class="mr-2 h-4 w-4" />
                    Delete
                  </Button>
                </div>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      <!-- Recent Activity -->
      <div class="grid gap-4 md:grid-cols-1">
        <!-- Recent Configurations -->
        <Card>
          <CardHeader>
            <CardTitle class="flex items-center gap-2">
              <Settings class="h-5 w-5" />
              Recent Configurations
            </CardTitle>
            <CardDescription> Latest CSV export setups </CardDescription>
          </CardHeader>
          <CardContent>
            <div v-if="recentConfigs.length === 0" class="py-8 text-center">
              <Settings class="mx-auto mb-2 h-8 w-8 text-muted-foreground" />
              <p class="text-sm text-muted-foreground">No configurations created yet</p>
              <Button size="sm" class="mt-2" @click="router.visit('/csv-config')">
                Create First Config
              </Button>
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="config in recentConfigs"
                :key="config.id"
                class="cursor-pointer rounded-lg border p-3 transition-colors hover:bg-muted/50"
                @click="router.visit('/export', { data: { configuration_id: config.id } })"
              >
                <div class="flex items-center justify-between">
                  <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">
                      {{ config.name }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                      {{ config.json_data?.original_filename || 'Unknown source' }}
                    </p>
                  </div>
                  <Badge variant="outline" class="text-xs">
                    {{ new Date(config.created_at).toLocaleDateString() }}
                  </Badge>
                </div>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  </AppLayout>
</template>

<script setup lang="ts">
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import AppLayout from '@/layouts/AppLayout.vue'
// import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Badge } from '@/components/ui/badge'
import { ScrollArea } from '@/components/ui/scroll-area'
import { Separator } from '@/components/ui/separator'
import { Switch } from '@/components/ui/switch'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import ValidationError from '@/components/ValidationError.vue'
import { apiGet, apiPost } from '@/composables/useApi'
import { dashboard } from '@/routes'
import { page as csvConfigPage } from '@/routes/csv/config'
import { page as exportPage } from '@/routes/export'
import {
  preview as previewJsonCsvConfig,
  store as storeJsonCsvConfig,
} from '@/routes/json/csv/config'
import { page as jsonUploadPage } from '@/routes/json/upload'
import { type BreadcrumbItem } from '@/types'
import { Head, router } from '@inertiajs/vue3'
import { AlertCircle, Download, Eye, FileText, Plus, Save, Settings, Trash2 } from 'lucide-vue-next'
import { computed, onMounted, ref, watch } from 'vue'

const breadcrumbs = computed((): BreadcrumbItem[] => [
  { title: 'Dashboard', href: dashboard().url },
  { title: 'CSV Configurations', href: props.json_data_id ? `/json-data/${props.json_data_id}/csv-config` : csvConfigPage().url },
  { title: 'New Configuration', href: '#' },
])

// Configuration form data
const config = ref({
  name: '',
  description: '',
  json_data_id: null as number | null,
  field_mappings: {} as Record<string, string>,
  transformations: {} as Record<string, any>,
  filters: [] as any[],
  column_order: [] as string[],
  include_headers: true,
  delimiter: ',',
  enclosure: '"',
  escape: '\\',
})

// UI state
const isLoading = ref(false)
const error = ref<string | null>(null)
const jsonData = ref<any>(null)
const availableFields = ref<string[]>([])
const suggestedMappings = ref<Record<string, string>>({})
const previewData = ref<any>(null)
const isPreviewLoading = ref(false)
const jsonDataList = ref<any[]>([])
const isLoadingJsonList = ref(false)

// Form state
const newCsvColumn = ref('')
const newSourceField = ref('')
const searchSuggestions = ref('')

const props = defineProps<{
  json_data_id?: number
}>()

const hasFieldMappings = computed(() => Object.keys(config.value.field_mappings).length > 0)

const availableSuggestions = computed(() => {
  const available: Record<string, string> = {}
  for (const [csvColumn, sourceField] of Object.entries(suggestedMappings.value)) {
    if (!config.value.field_mappings[csvColumn]) {
      available[csvColumn] = sourceField
    }
  }
  return available
})

const filteredSuggestions = computed(() => {
  if (!searchSuggestions.value.trim()) {
    return availableSuggestions.value
  }

  const search = searchSuggestions.value.toLowerCase()
  const filtered: Record<string, string> = {}

  for (const [csvColumn, sourceField] of Object.entries(availableSuggestions.value)) {
    if (
      csvColumn.toLowerCase().includes(search) ||
      sourceField.toLowerCase().includes(search)
    ) {
      filtered[csvColumn] = sourceField
    }
  }

  return filtered
})

// Simple validation state
const nameError = ref('')
const nameValidated = ref(false)
const fieldMappingsError = ref('')
const fieldMappingsValidated = ref(false)

// Simple validation functions
const validateName = (): boolean => {
  nameValidated.value = true

  if (!config.value.name?.trim()) {
    nameError.value = 'Configuration name is required'
    return false
  }

  if (config.value.name.trim().length < 2) {
    nameError.value = 'Configuration name must be at least 2 characters'
    return false
  }

  if (config.value.name.trim().length > 255) {
    nameError.value = 'Configuration name must be less than 255 characters'
    return false
  }

  nameError.value = ''
  return true
}

const validateFieldMappings = (): boolean => {
  fieldMappingsValidated.value = true

  if (!hasFieldMappings.value) {
    fieldMappingsError.value = 'At least one field mapping is required'
    return false
  }

  fieldMappingsError.value = ''
  return true
}

// Computed properties
const canSave = computed(() => {
  return (
    hasFieldMappings.value &&
    config.value.name?.trim() &&
    config.value.json_data_id &&
    !isLoading.value
  )
})

async function loadJsonData(id: number) {
  if (!id) return

  try {
    isLoading.value = true
    const response = await apiGet(`/api/json/${id}`)
    if (!response.ok) throw new Error('Failed to load JSON data')

    jsonData.value = await response.json()
    config.value.json_data_id = id

    // Load suggestions
    await loadSuggestions(id)
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to load JSON data'
  } finally {
    isLoading.value = false
  }
}

async function loadSuggestions(id: number) {
  try {
    const response = await apiGet(`/api/json/${id}/suggest`)
    if (!response.ok) throw new Error('Failed to load suggestions')

    const data = await response.json()
    availableFields.value = data.available_fields
    suggestedMappings.value = data.suggested_mappings
  } catch (err) {
    console.error('Failed to load suggestions:', err)
  }
}

async function loadJsonDataList() {
  try {
    isLoadingJsonList.value = true
    const response = await apiGet('/api/json')
    if (!response.ok) throw new Error('Failed to load JSON data list')

    jsonDataList.value = await response.json()
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to load JSON data list'
  } finally {
    isLoadingJsonList.value = false
  }
}

function selectJsonData(jsonDataId: number) {
  router.visit(`/json-data/${jsonDataId}/csv-config`)
}

function applySuggestions() {
  config.value.field_mappings = { ...suggestedMappings.value }
  config.value.column_order = Object.keys(suggestedMappings.value)

  // Clear field mappings error after applying suggestions
  if (fieldMappingsValidated.value) {
    validateFieldMappings()
  }
}

function addSingleMapping(csvColumn: string, sourceField: string) {
  // Don't add if already exists
  if (config.value.field_mappings[csvColumn]) return

  config.value.field_mappings[csvColumn] = sourceField

  if (!config.value.column_order.includes(csvColumn)) {
    config.value.column_order.push(csvColumn)
  }

  // Clear field mappings error after adding
  if (fieldMappingsValidated.value) {
    validateFieldMappings()
  }
}

function addFieldMapping() {
  if (!newCsvColumn.value || !newSourceField.value) return

  config.value.field_mappings[newCsvColumn.value] = newSourceField.value

  if (!config.value.column_order.includes(newCsvColumn.value)) {
    config.value.column_order.push(newCsvColumn.value)
  }

  // Clear field mappings error after adding
  if (fieldMappingsValidated.value) {
    validateFieldMappings()
  }

  newCsvColumn.value = ''
  newSourceField.value = ''
}

function removeFieldMapping(csvColumn: string) {
  delete config.value.field_mappings[csvColumn]
  config.value.column_order = config.value.column_order.filter(col => col !== csvColumn)

  // Re-validate field mappings after removing
  if (fieldMappingsValidated.value) {
    validateFieldMappings()
  }
}

async function previewCsv() {
  if (!hasFieldMappings.value || !config.value.json_data_id) return

  try {
    isPreviewLoading.value = true
    const response = await apiPost(previewJsonCsvConfig(config.value.json_data_id).url, {
      field_mappings: config.value.field_mappings,
      transformations: config.value.transformations,
      filters: config.value.filters,
      limit: 5,
    })

    if (!response.ok) throw new Error('Failed to generate preview')

    previewData.value = await response.json()
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to generate preview'
  } finally {
    isPreviewLoading.value = false
  }
}

async function saveConfiguration() {
  // Validate all fields
  const nameValid = validateName()
  const mappingsValid = validateFieldMappings()

  if (!nameValid || !mappingsValid) {
    error.value = 'Please correct the validation errors before saving'
    return
  }

  try {
    isLoading.value = true
    const { json_data_id, ...configData } = config.value
    if (!json_data_id) throw 'json_data_id missing'
    const response = await apiPost(storeJsonCsvConfig(json_data_id).url, configData)

    if (!response.ok) {
      const errorData = await response.json()
      throw new Error(errorData.message || 'Failed to save configuration')
    }

    const result = await response.json()
    router.visit(exportPage().url, {
      data: { configuration_id: result.id },
    })
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Failed to save configuration'
  } finally {
    isLoading.value = false
  }
}

async function quickExport() {
  if (!hasFieldMappings.value || !config.value.json_data_id) return

  try {
    const response = await apiPost('/api/export/quick', {
      json_data_id: config.value.json_data_id,
      field_mappings: config.value.field_mappings,
      transformations: config.value.transformations,
      filters: config.value.filters,
      filename: config.value.name || 'export',
      include_headers: config.value.include_headers,
      delimiter: config.value.delimiter,
      enclosure: config.value.enclosure,
      escape: config.value.escape,
    })

    if (!response.ok) {
      const errorData = await response.json()
      throw new Error(errorData.message || 'Export failed')
    }

    // Trigger download
    const blob = await response.blob()
    const url = window.URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = (config.value.name || 'export') + '.csv'
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    window.URL.revokeObjectURL(url)
  } catch (err) {
    error.value = err instanceof Error ? err.message : 'Export failed'
  }
}

// Watch for field mappings changes to trigger preview
watch(
  () => config.value.field_mappings,
  () => {
    if (hasFieldMappings.value) {
      previewCsv()
    }
  },
  { deep: true }
)

onMounted(() => {
  // Only use props.json_data_id now - no more query parameter support
  if (props.json_data_id) {
    loadJsonData(props.json_data_id)
  } else {
    loadJsonDataList()
  }
})
</script>

<template>
  <Head title="CSV Configuration" />

  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="flex h-full flex-1 flex-col gap-6 p-6">
      <div class="flex flex-col gap-2">
        <h1 class="text-2xl font-bold">Create CSV Export Configuration</h1>
        <p class="text-muted-foreground">
          Map JSON fields to CSV columns and configure export settings
        </p>
      </div>

      <!-- JSON Data Selection Table -->
      <div v-if="!jsonData && !isLoading">
        <div v-if="isLoadingJsonList" class="py-12 text-center">
          <p class="text-lg font-medium">Loading JSON data...</p>
        </div>

        <div v-else-if="jsonDataList.length === 0" class="py-12 text-center">
          <FileText class="mx-auto mb-4 h-16 w-16 text-muted-foreground" />
          <p class="mb-2 text-lg font-medium">No JSON data found</p>
          <p class="mb-4 text-muted-foreground">Upload a JSON file first to configure CSV export</p>
          <Button @click="router.visit(jsonUploadPage().url)"> Upload JSON File </Button>
        </div>

        <div v-else class="space-y-6">
          <div class="flex items-center justify-between">
            <div>
              <h2 class="text-xl font-semibold">Select JSON Data</h2>
              <p class="text-muted-foreground">Choose a JSON file to configure CSV export</p>
            </div>
            <Button @click="router.visit(jsonUploadPage().url)" variant="outline">
              <Plus class="mr-2 h-4 w-4" />
              Upload New File
            </Button>
          </div>

          <Card>
            <CardContent class="p-0">
              <Table>
                <TableHeader>
                  <TableRow>
                    <TableHead>Filename</TableHead>
                    <TableHead>Records</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Uploaded</TableHead>
                  </TableRow>
                </TableHeader>
                <TableBody>
                  <TableRow
                    v-for="jsonFile in jsonDataList"
                    :key="jsonFile.id"
                    class="cursor-pointer transition-colors hover:bg-muted/50"
                    @click="selectJsonData(jsonFile.id)"
                  >
                    <TableCell class="font-medium">{{ jsonFile.original_filename }}</TableCell>
                    <TableCell>{{ jsonFile.record_count?.toLocaleString() || 'N/A' }}</TableCell>
                    <TableCell>
                      <Badge :variant="jsonFile.status === 'processed' ? 'secondary' : 'destructive'">
                        {{ jsonFile.status }}
                      </Badge>
                    </TableCell>
                    <TableCell class="text-muted-foreground">
                      {{ new Date(jsonFile.created_at).toLocaleDateString() }}
                    </TableCell>
                  </TableRow>
                </TableBody>
              </Table>
            </CardContent>
          </Card>
        </div>
      </div>

      <div v-else class="grid grid-cols-1 gap-6 xl:grid-cols-3">
        <!-- Configuration Form -->
        <div class="space-y-6 xl:col-span-2">
          <!-- Basic Settings -->
          <Card>
            <CardHeader>
              <CardTitle class="flex items-center gap-2">
                <Settings class="h-5 w-5" />
                Basic Settings
              </CardTitle>
              <CardDescription> Configure the basic export settings </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
              <div class="grid grid-cols-2 gap-4">
                <div class="space-y-2">
                  <Label for="name">Configuration Name *</Label>
                  <Input
                    id="name"
                    v-model="config.name"
                    placeholder="e.g., User Export"
                    required
                    @blur="validateName"
                  />
                  <ValidationError :message="nameError" :show="nameValidated && !!nameError" />
                </div>
                <div class="space-y-2">
                  <Label>Source File</Label>
                  <Input :value="jsonData?.filename || 'Loading...'" disabled class="bg-muted" />
                </div>
              </div>

              <div class="space-y-2">
                <Label for="description">Description</Label>
                <Textarea
                  id="description"
                  v-model="config.description"
                  placeholder="Optional description of this configuration"
                  rows="2"
                />
              </div>

              <div class="grid grid-cols-4 gap-4">
                <div class="space-y-2">
                  <Label for="delimiter">Delimiter</Label>
                  <select
                    id="delimiter"
                    v-model="config.delimiter"
                    class="flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-sm whitespace-nowrap shadow-sm ring-offset-background placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    <option value=",">,</option>
                    <option value=";">;</option>
                    <option value="\t">Tab</option>
                    <option value="|">|</option>
                  </select>
                </div>
                <div class="space-y-2">
                  <Label for="enclosure">Enclosure</Label>
                  <Input id="enclosure" v-model="config.enclosure" maxlength="1" />
                </div>
                <div class="space-y-2">
                  <Label for="escape">Escape</Label>
                  <Input id="escape" v-model="config.escape" maxlength="1" />
                </div>
                <div class="space-y-2">
                  <Label for="headers">Include Headers</Label>
                  <div class="flex items-center pt-2">
                    <Switch id="headers" v-model:checked="config.include_headers" />
                  </div>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Field Mappings -->
          <Card>
            <CardHeader>
              <CardTitle>Field Mappings</CardTitle>
              <CardDescription> Map JSON fields to CSV columns </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
              <!-- Suggestions -->
              <div v-if="Object.keys(suggestedMappings).length > 0" class="space-y-2">
                <div class="flex items-center justify-between">
                  <Label>Suggested Mappings</Label>
                  <Button
                    size="sm"
                    variant="outline"
                    @click="applySuggestions"
                    :disabled="Object.keys(availableSuggestions).length === 0"
                  >
                    Apply All ({{ Object.keys(availableSuggestions).length }})
                  </Button>
                </div>

                <!-- Search field for filtering suggestions -->
                <div v-if="Object.keys(availableSuggestions).length > 0" class="space-y-2">
                  <Input
                    v-model="searchSuggestions"
                    placeholder="Search suggestions by column or field name..."
                    class="text-sm"
                  />
                </div>

                <div class="flex flex-wrap gap-2">
                  <!-- Available suggestions (clickable) - now filtered -->
                  <Badge
                    v-for="(sourceField, csvColumn) in filteredSuggestions"
                    :key="`available-${csvColumn}`"
                    variant="outline"
                    class="cursor-pointer text-xs transition-colors hover:border-primary hover:bg-primary/10"
                    @click="addSingleMapping(csvColumn, sourceField)"
                    :title="`Click to add: ${csvColumn} ← ${sourceField}`"
                  >
                    {{ csvColumn }} ← {{ sourceField }}
                  </Badge>
                  <!-- Already applied suggestions (non-clickable) -->
                  <Badge
                    v-for="(sourceField, csvColumn) in suggestedMappings"
                    :key="`applied-${csvColumn}`"
                    v-show="config.field_mappings[csvColumn]"
                    variant="secondary"
                    class="text-xs opacity-50"
                    :title="`Already added: ${csvColumn} ← ${sourceField}`"
                  >
                    {{ csvColumn }} ← {{ sourceField }} ✓
                  </Badge>
                </div>

                <!-- Show message when search yields no results -->
                <div v-if="searchSuggestions.trim() && Object.keys(filteredSuggestions).length === 0" class="text-center py-4">
                  <p class="text-sm text-muted-foreground">
                    No suggestions match "{{ searchSuggestions }}"
                  </p>
                </div>
              </div>

              <Separator />

              <!-- Add New Mapping -->
              <div class="grid grid-cols-12 items-end gap-2">
                <div class="col-span-5 space-y-2">
                  <Label for="csvColumn">CSV Column Name</Label>
                  <Input id="csvColumn" v-model="newCsvColumn" placeholder="Column name" />
                </div>
                <div class="col-span-5 space-y-2">
                  <Label for="sourceField">Source Field</Label>
                  <select
                    id="sourceField"
                    v-model="newSourceField"
                    class="flex h-9 w-full items-center justify-between rounded-md border border-input bg-transparent px-3 py-2 text-sm whitespace-nowrap shadow-sm ring-offset-background placeholder:text-muted-foreground focus:ring-1 focus:ring-ring focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                  >
                    <option value="">Select field</option>
                    <option v-for="field in availableFields" :key="field" :value="field">
                      {{ field }}
                    </option>
                  </select>
                </div>
                <div class="col-span-2">
                  <Button
                    size="sm"
                    @click="addFieldMapping"
                    :disabled="!newCsvColumn || !newSourceField"
                    class="w-full"
                  >
                    <Plus class="h-4 w-4" />
                  </Button>
                </div>
              </div>

              <!-- Field Mappings Validation Error -->
              <ValidationError
                :message="fieldMappingsError"
                :show="fieldMappingsValidated && !!fieldMappingsError"
              />

              <!-- Current Mappings -->
              <div v-if="hasFieldMappings" class="space-y-2">
                <Label>Current Mappings</Label>
                <div class="rounded-lg border">
                  <Table>
                    <TableHeader>
                      <TableRow>
                        <TableHead>CSV Column</TableHead>
                        <TableHead>Source Field</TableHead>
                        <TableHead class="w-20">Actions</TableHead>
                      </TableRow>
                    </TableHeader>
                    <TableBody>
                      <TableRow
                        v-for="(sourceField, csvColumn) in config.field_mappings"
                        :key="csvColumn"
                      >
                        <TableCell class="font-medium">{{ csvColumn }}</TableCell>
                        <TableCell class="font-mono text-sm">{{ sourceField }}</TableCell>
                        <TableCell>
                          <Button size="sm" variant="ghost" @click="removeFieldMapping(csvColumn)">
                            <Trash2 class="h-4 w-4" />
                          </Button>
                        </TableCell>
                      </TableRow>
                    </TableBody>
                  </Table>
                </div>
              </div>
            </CardContent>
          </Card>

          <!-- Actions -->
          <div class="flex flex-wrap gap-3">
            <Button
              @click="saveConfiguration"
              :disabled="isLoading"
              :variant="canSave ? 'default' : 'secondary'"
            >
              <Save class="mr-2 h-4 w-4" />
              Save Configuration
            </Button>
            <Button variant="outline" @click="quickExport" :disabled="!hasFieldMappings">
              <Download class="mr-2 h-4 w-4" />
              Quick Export
            </Button>
            <Button
              variant="outline"
              @click="previewCsv"
              :disabled="!hasFieldMappings || isPreviewLoading"
            >
              <Eye class="mr-2 h-4 w-4" />
              {{ isPreviewLoading ? 'Loading...' : 'Preview' }}
            </Button>
          </div>

          <!-- Error Message -->
          <Alert v-if="error" variant="destructive">
            <AlertCircle class="h-4 w-4" />
            <AlertDescription>{{ error }}</AlertDescription>
          </Alert>
        </div>

        <!-- Preview Panel -->
        <div class="space-y-6">
          <!-- JSON Info -->
          <Card v-if="jsonData">
            <CardHeader>
              <CardTitle class="text-sm">Source Data</CardTitle>
            </CardHeader>
            <CardContent class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-muted-foreground">Records:</span>
                <span class="font-medium">{{ jsonData.record_count }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-muted-foreground">Available Fields:</span>
                <span class="font-medium">{{ availableFields.length }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-muted-foreground">Status:</span>
                <Badge variant="secondary">{{ jsonData.status }}</Badge>
              </div>
            </CardContent>
          </Card>

          <!-- CSV Preview -->
          <Card v-if="previewData">
            <CardHeader>
              <CardTitle class="text-sm">CSV Preview</CardTitle>
              <CardDescription class="text-xs">
                Showing {{ previewData.preview_records }} of {{ previewData.total_records }} records
              </CardDescription>
            </CardHeader>
            <CardContent>
              <ScrollArea class="h-64 w-full rounded border">
                <Table>
                  <TableHeader v-if="config.include_headers">
                    <TableRow>
                      <TableHead
                        v-for="header in previewData.headers"
                        :key="header"
                        class="text-xs"
                      >
                        {{ header }}
                      </TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    <TableRow v-for="(row, index) in previewData.rows" :key="index">
                      <TableCell
                        v-for="(cell, cellIndex) in row"
                        :key="cellIndex"
                        class="max-w-32 truncate text-xs"
                        :title="String(cell)"
                      >
                        {{ cell }}
                      </TableCell>
                    </TableRow>
                  </TableBody>
                </Table>
              </ScrollArea>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

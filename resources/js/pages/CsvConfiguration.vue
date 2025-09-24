<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
// import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Separator } from '@/components/ui/separator';
import { ScrollArea } from '@/components/ui/scroll-area';
import { AlertCircle, Plus, Trash2, Eye, Save, Settings, FileText, Download } from 'lucide-vue-next';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { type BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, watch } from 'vue';
import { apiGet, apiPost } from '@/composables/useApi';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'CSV Configuration', href: route('csv.config.page') },
];

const page = usePage();

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
    escape: '\\'
});

// UI state
const isLoading = ref(false);
const error = ref<string | null>(null);
const jsonData = ref<any>(null);
const availableFields = ref<string[]>([]);
const suggestedMappings = ref<Record<string, string>>({});
const previewData = ref<any>(null);
const isPreviewLoading = ref(false);

// Form state
const newCsvColumn = ref('');
const newSourceField = ref('');

const props = defineProps<{
    json_data_id?: number;
}>();

const hasFieldMappings = computed(() => Object.keys(config.value.field_mappings).length > 0);

async function loadJsonData(id: number) {
    if (!id) return;

    try {
        isLoading.value = true;
        const response = await apiGet(`/api/json/${id}`);
        if (!response.ok) throw new Error('Failed to load JSON data');

        jsonData.value = await response.json();
        config.value.json_data_id = id;

        // Load suggestions
        await loadSuggestions(id);
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'Failed to load JSON data';
    } finally {
        isLoading.value = false;
    }
}

async function loadSuggestions(id: number) {
    try {
        const response = await apiGet(`/api/json/${id}/suggest`);
        if (!response.ok) throw new Error('Failed to load suggestions');

        const data = await response.json();
        availableFields.value = data.available_fields;
        suggestedMappings.value = data.suggested_mappings;
    } catch (err) {
        console.error('Failed to load suggestions:', err);
    }
}

function applySuggestions() {
    config.value.field_mappings = { ...suggestedMappings.value };
    config.value.column_order = Object.keys(suggestedMappings.value);
}

function addFieldMapping() {
    if (!newCsvColumn.value || !newSourceField.value) return;

    config.value.field_mappings[newCsvColumn.value] = newSourceField.value;

    if (!config.value.column_order.includes(newCsvColumn.value)) {
        config.value.column_order.push(newCsvColumn.value);
    }

    newCsvColumn.value = '';
    newSourceField.value = '';
}

function removeFieldMapping(csvColumn: string) {
    delete config.value.field_mappings[csvColumn];
    config.value.column_order = config.value.column_order.filter(col => col !== csvColumn);
}

async function previewCsv() {
    if (!hasFieldMappings.value || !config.value.json_data_id) return;

    try {
        isPreviewLoading.value = true;
        const response = await apiPost('/api/csv-config/preview', {
            json_data_id: config.value.json_data_id,
            field_mappings: config.value.field_mappings,
            transformations: config.value.transformations,
            filters: config.value.filters,
            limit: 5
        });

        if (!response.ok) throw new Error('Failed to generate preview');

        previewData.value = await response.json();
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'Failed to generate preview';
    } finally {
        isPreviewLoading.value = false;
    }
}

async function saveConfiguration() {
    if (!config.value.name || !hasFieldMappings.value || !config.value.json_data_id) {
        error.value = 'Please provide a name and configure field mappings';
        return;
    }

    try {
        isLoading.value = true;
        const response = await fetch('/api/csv-config', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify(config.value)
        });

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || 'Failed to save configuration');
        }

        const result = await response.json();
        router.visit(route('export.page'), {
            data: { configuration_id: result.id }
        });
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'Failed to save configuration';
    } finally {
        isLoading.value = false;
    }
}

async function quickExport() {
    if (!hasFieldMappings.value || !config.value.json_data_id) return;

    try {
        const response = await fetch('/api/export/quick', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                json_data_id: config.value.json_data_id,
                field_mappings: config.value.field_mappings,
                transformations: config.value.transformations,
                filters: config.value.filters,
                filename: config.value.name || 'export',
                include_headers: config.value.include_headers,
                delimiter: config.value.delimiter,
                enclosure: config.value.enclosure,
                escape: config.value.escape
            })
        });

        if (!response.ok) throw new Error('Export failed');

        // Trigger download
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = (config.value.name || 'export') + '.csv';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'Export failed';
    }
}

// Watch for field mappings changes to trigger preview
watch(() => config.value.field_mappings, () => {
    if (hasFieldMappings.value) {
        previewCsv();
    }
}, { deep: true });

onMounted(() => {
    const jsonDataId = props.json_data_id || Number(new URLSearchParams(window.location.search).get('json_data_id'));
    if (jsonDataId) {
        loadJsonData(jsonDataId);
    }
});
</script>

<template>
    <Head title="CSV Configuration" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl font-bold">Configure CSV Export</h1>
                <p class="text-muted-foreground">
                    Map JSON fields to CSV columns and configure export settings
                </p>
            </div>

            <div v-if="!jsonData && !isLoading" class="text-center py-12">
                <FileText class="h-16 w-16 mx-auto text-muted-foreground mb-4" />
                <p class="text-lg font-medium mb-2">No JSON data selected</p>
                <p class="text-muted-foreground mb-4">Upload a JSON file first to configure CSV export</p>
                <Button @click="router.visit(route('json.upload.page'))">
                    Upload JSON File
                </Button>
            </div>

            <div v-else class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <!-- Configuration Form -->
                <div class="xl:col-span-2 space-y-6">
                    <!-- Basic Settings -->
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Settings class="h-5 w-5" />
                                Basic Settings
                            </CardTitle>
                            <CardDescription>
                                Configure the basic export settings
                            </CardDescription>
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
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>Source File</Label>
                                    <Input
                                        :value="jsonData?.filename || 'Loading...'"
                                        disabled
                                        class="bg-muted"
                                    />
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
                                        class="flex h-9 w-full items-center justify-between whitespace-nowrap rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <option value=",">,</option>
                                        <option value=";">;</option>
                                        <option value="\t">Tab</option>
                                        <option value="|">|</option>
                                    </select>
                                </div>
                                <div class="space-y-2">
                                    <Label for="enclosure">Enclosure</Label>
                                    <Input
                                        id="enclosure"
                                        v-model="config.enclosure"
                                        maxlength="1"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label for="escape">Escape</Label>
                                    <Input
                                        id="escape"
                                        v-model="config.escape"
                                        maxlength="1"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label for="headers">Include Headers</Label>
                                    <div class="flex items-center pt-2">
                                        <Switch
                                            id="headers"
                                            v-model:checked="config.include_headers"
                                        />
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Field Mappings -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Field Mappings</CardTitle>
                            <CardDescription>
                                Map JSON fields to CSV columns
                            </CardDescription>
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
                                    >
                                        Apply All
                                    </Button>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <Badge
                                        v-for="(sourceField, csvColumn) in suggestedMappings"
                                        :key="csvColumn"
                                        variant="outline"
                                        class="text-xs"
                                    >
                                        {{ csvColumn }} ← {{ sourceField }}
                                    </Badge>
                                </div>
                            </div>

                            <Separator />

                            <!-- Add New Mapping -->
                            <div class="grid grid-cols-12 gap-2 items-end">
                                <div class="col-span-5 space-y-2">
                                    <Label for="csvColumn">CSV Column Name</Label>
                                    <Input
                                        id="csvColumn"
                                        v-model="newCsvColumn"
                                        placeholder="Column name"
                                    />
                                </div>
                                <div class="col-span-5 space-y-2">
                                    <Label for="sourceField">Source Field</Label>
                                    <select
                                        id="sourceField"
                                        v-model="newSourceField"
                                        class="flex h-9 w-full items-center justify-between whitespace-nowrap rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                    >
                                        <option value="">Select field</option>
                                        <option
                                            v-for="field in availableFields"
                                            :key="field"
                                            :value="field"
                                        >
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

                            <!-- Current Mappings -->
                            <div v-if="hasFieldMappings" class="space-y-2">
                                <Label>Current Mappings</Label>
                                <div class="border rounded-lg">
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
                                                    <Button
                                                        size="sm"
                                                        variant="ghost"
                                                        @click="removeFieldMapping(csvColumn)"
                                                    >
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
                            :disabled="!config.name || !hasFieldMappings || isLoading"
                        >
                            <Save class="h-4 w-4 mr-2" />
                            Save Configuration
                        </Button>
                        <Button
                            variant="outline"
                            @click="quickExport"
                            :disabled="!hasFieldMappings"
                        >
                            <Download class="h-4 w-4 mr-2" />
                            Quick Export
                        </Button>
                        <Button
                            variant="outline"
                            @click="previewCsv"
                            :disabled="!hasFieldMappings || isPreviewLoading"
                        >
                            <Eye class="h-4 w-4 mr-2" />
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
                            <ScrollArea class="h-64 w-full border rounded">
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
                                        <TableRow
                                            v-for="(row, index) in previewData.rows"
                                            :key="index"
                                        >
                                            <TableCell
                                                v-for="(cell, cellIndex) in row"
                                                :key="cellIndex"
                                                class="text-xs max-w-32 truncate"
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
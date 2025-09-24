<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Checkbox } from '@/components/ui/checkbox';
// import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { AlertCircle, Download, FileText, Archive, Trash2, Copy, Settings, Eye } from 'lucide-vue-next';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { type BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { ref, computed, onMounted } from 'vue';
import { dashboard } from '@/routes';
import { page as exportPage, download } from '@/routes/export';
import { page as csvConfigPage } from '@/routes/csv/config';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard().url },
    { title: 'Export', href: exportPage().url },
];

const props = defineProps<{
    configuration_id?: number;
}>();

// State
const configurations = ref<any[]>([]);
const exports = ref<any[]>([]);
const selectedConfigs = ref<number[]>([]);
const isLoading = ref(false);
const error = ref<string | null>(null);
const exportFormat = ref<'single' | 'multiple'>('single');

const hasSelectedConfigs = computed(() => selectedConfigs.value.length > 0);
const allConfigsSelected = computed(() =>
    configurations.value.length > 0 && selectedConfigs.value.length === configurations.value.length
);

async function loadConfigurations() {
    try {
        isLoading.value = true;
        const response = await fetch('/api/csv-config');
        if (!response.ok) throw new Error('Failed to load configurations');

        configurations.value = await response.json();

        // Auto-select if configuration_id provided
        if (props.configuration_id) {
            selectedConfigs.value = [props.configuration_id];
        }
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'Failed to load configurations';
    } finally {
        isLoading.value = false;
    }
}

async function loadExports() {
    try {
        const response = await fetch('/api/export/list');
        if (!response.ok) throw new Error('Failed to load exports');

        exports.value = await response.json();
    } catch (err) {
        console.error('Failed to load exports:', err);
    }
}

function toggleConfigSelection(configId: number) {
    const index = selectedConfigs.value.indexOf(configId);
    if (index > -1) {
        selectedConfigs.value.splice(index, 1);
    } else {
        selectedConfigs.value.push(configId);
    }
}

function toggleAllConfigs() {
    if (allConfigsSelected.value) {
        selectedConfigs.value = [];
    } else {
        selectedConfigs.value = configurations.value.map(config => config.id);
    }
}

async function exportSingle(configId: number) {
    try {
        const response = await fetch(`/api/export/single/${configId}`);
        if (!response.ok) throw new Error('Export failed');

        // Trigger download
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `export_${Date.now()}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        // Refresh exports list
        await loadExports();
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'Export failed';
    }
}

async function exportMultiple() {
    if (!hasSelectedConfigs.value) return;

    try {
        isLoading.value = true;
        const response = await fetch('/api/export/multiple', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            },
            body: JSON.stringify({
                configuration_ids: selectedConfigs.value
            })
        });

        if (!response.ok) throw new Error('Bulk export failed');

        // Trigger download
        const blob = await response.blob();
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `bulk_export_${Date.now()}.zip`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);

        // Refresh exports list
        await loadExports();
        selectedConfigs.value = [];
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'Bulk export failed';
    } finally {
        isLoading.value = false;
    }
}

async function duplicateConfiguration(configId: number) {
    try {
        const response = await fetch(`/api/csv-config/${configId}/duplicate`, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            }
        });

        if (!response.ok) throw new Error('Failed to duplicate configuration');

        await loadConfigurations();
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'Failed to duplicate configuration';
    }
}

async function deleteConfiguration(configId: number) {
    if (!confirm('Are you sure you want to delete this configuration?')) return;

    try {
        const response = await fetch(`/api/csv-config/${configId}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            }
        });

        if (!response.ok) throw new Error('Failed to delete configuration');

        await loadConfigurations();
        selectedConfigs.value = selectedConfigs.value.filter(id => id !== configId);
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'Failed to delete configuration';
    }
}

async function deleteExport(filename: string) {
    if (!confirm('Are you sure you want to delete this export?')) return;

    try {
        const response = await fetch(`/api/export/${filename}`, {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
            }
        });

        if (!response.ok) throw new Error('Failed to delete export');

        await loadExports();
    } catch (err) {
        error.value = err instanceof Error ? err.message : 'Failed to delete export';
    }
}

function downloadExport(filename: string) {
    window.open(download(filename).url, '_blank');
}

function formatFileSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

onMounted(() => {
    loadConfigurations();
    loadExports();
});
</script>

<template>
    <Head title="Export" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl font-bold">Export Manager</h1>
                <p class="text-muted-foreground">
                    Export your configurations and manage downloads
                </p>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <!-- Configurations -->
                <div class="xl:col-span-2 space-y-6">
                    <Card>
                        <CardHeader>
                            <div class="flex items-center justify-between">
                                <div>
                                    <CardTitle class="flex items-center gap-2">
                                        <Settings class="h-5 w-5" />
                                        CSV Configurations
                                    </CardTitle>
                                    <CardDescription>
                                        Select configurations to export
                                    </CardDescription>
                                </div>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    @click="router.visit(csvConfigPage().url)"
                                >
                                    New Configuration
                                </Button>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <div v-if="configurations.length === 0" class="text-center py-8">
                                <FileText class="h-16 w-16 mx-auto text-muted-foreground mb-4" />
                                <p class="text-lg font-medium mb-2">No configurations found</p>
                                <p class="text-muted-foreground mb-4">Create a CSV configuration first</p>
                                <Button @click="router.visit(csvConfigPage().url)">
                                    Create Configuration
                                </Button>
                            </div>

                            <div v-else class="space-y-4">
                                <!-- Bulk Actions -->
                                <div class="flex items-center justify-between pb-4 border-b">
                                    <div class="flex items-center gap-3">
                                        <Checkbox
                                            :checked="allConfigsSelected"
                                            @update:checked="toggleAllConfigs"
                                        />
                                        <span class="text-sm text-muted-foreground">
                                            {{ selectedConfigs.length }} of {{ configurations.length }} selected
                                        </span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <Button
                                            size="sm"
                                            @click="exportMultiple"
                                            :disabled="!hasSelectedConfigs || isLoading"
                                        >
                                            <Archive class="h-4 w-4 mr-2" />
                                            Export Selected ({{ selectedConfigs.length }})
                                        </Button>
                                    </div>
                                </div>

                                <!-- Configuration List -->
                                <div class="space-y-3">
                                    <div
                                        v-for="config in configurations"
                                        :key="config.id"
                                        class="p-4 border rounded-lg hover:bg-muted/50 transition-colors"
                                    >
                                        <div class="flex items-start gap-3">
                                            <Checkbox
                                                :checked="selectedConfigs.includes(config.id)"
                                                @update:checked="() => toggleConfigSelection(config.id)"
                                            />
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center justify-between mb-2">
                                                    <h3 class="font-medium truncate">{{ config.name }}</h3>
                                                    <Badge variant="secondary" class="text-xs">
                                                        {{ config.json_data?.original_filename || 'Unknown' }}
                                                    </Badge>
                                                </div>
                                                <p v-if="config.description" class="text-sm text-muted-foreground mb-3 line-clamp-2">
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
                                                            @click="router.visit(csvConfigPage().url, { data: { config_id: config.id } })"
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
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            @click="exportSingle(config.id)"
                                                        >
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

                <!-- Export History -->
                <div>
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Download class="h-5 w-5" />
                                Export History
                            </CardTitle>
                            <CardDescription>
                                Recent exports and downloads
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="exports.length === 0" class="text-center py-8">
                                <Archive class="h-8 w-8 mx-auto text-muted-foreground mb-2" />
                                <p class="text-sm text-muted-foreground">No exports yet</p>
                            </div>

                            <div v-else class="space-y-3">
                                <div
                                    v-for="exportFile in exports"
                                    :key="exportFile.filename"
                                    class="p-3 border rounded-lg hover:bg-muted/50 transition-colors"
                                >
                                    <div class="flex items-start justify-between">
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium truncate">
                                                {{ exportFile.filename }}
                                            </p>
                                            <div class="flex items-center gap-2 mt-1">
                                                <span class="text-xs text-muted-foreground">
                                                    {{ formatFileSize(exportFile.size) }}
                                                </span>
                                                <span class="text-xs text-muted-foreground">
                                                    {{ exportFile.created_at }}
                                                </span>
                                            </div>
                                        </div>
                                        <div class="flex items-center gap-1">
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                @click="downloadExport(exportFile.filename)"
                                            >
                                                <Download class="h-4 w-4" />
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                @click="deleteExport(exportFile.filename)"
                                            >
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
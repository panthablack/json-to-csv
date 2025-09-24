<script setup lang="ts">
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { AlertCircle, FileText, Upload } from 'lucide-vue-next';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { type BreadcrumbItem } from '@/types';
import { Head, usePage } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';

const page = usePage();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: route('dashboard') },
    { title: 'JSON Upload', href: route('json.upload.page') },
];

const file = ref<File | null>(null);
const isDragging = ref(false);
const isUploading = ref(false);
const uploadProgress = ref(0);
const error = ref<string | null>(null);
const uploadedFiles = ref<any[]>([]);

const fileInput = ref<HTMLInputElement>();

const isValidFile = computed(() => {
    if (!file.value) return false;
    return file.value.type === 'application/json' || file.value.name.endsWith('.json');
});

const fileSize = computed(() => {
    if (!file.value) return '';
    const size = file.value.size;
    if (size < 1024) return `${size} bytes`;
    if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
    return `${(size / (1024 * 1024)).toFixed(1)} MB`;
});

function handleFileSelect(event: Event) {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        file.value = target.files[0];
        error.value = null;
    }
}

function handleDrop(event: DragEvent) {
    isDragging.value = false;
    const files = event.dataTransfer?.files;
    if (files && files[0]) {
        file.value = files[0];
        error.value = null;
    }
}

function handleDragOver(event: DragEvent) {
    event.preventDefault();
    isDragging.value = true;
}

function handleDragLeave() {
    isDragging.value = false;
}

function removeFile() {
    file.value = null;
    error.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function openFileDialog() {
    if (fileInput.value && typeof fileInput.value.click === 'function') {
        fileInput.value.click();
    }
}

async function uploadFile() {
    if (!file.value || !isValidFile.value) return;

    isUploading.value = true;
    uploadProgress.value = 0;
    error.value = null;

    const formData = new FormData();
    formData.append('file', file.value);

    try {
        const progressInterval = setInterval(() => {
            if (uploadProgress.value < 90) {
                uploadProgress.value += 10;
            }
        }, 100);

        // Get CSRF token from page props or meta tag
        const token = (page.props as any).csrf_token ||
                     document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const response = await fetch('/api/json', {
            method: 'POST',
            body: formData,
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
        });

        clearInterval(progressInterval);
        uploadProgress.value = 100;

        if (!response.ok) {
            const errorData = await response.json();
            throw new Error(errorData.message || errorData.error || 'Upload failed');
        }

        const result = await response.json();

        // Redirect to CSV configuration page with the JSON data ID
        router.visit(route('csv.config.page'), {
            data: { json_data_id: result.id }
        });

    } catch (err) {
        error.value = err instanceof Error ? err.message : 'Upload failed';
        uploadProgress.value = 0;
    } finally {
        isUploading.value = false;
    }
}

async function loadUploadedFiles() {
    try {
        const token = (page.props as any).csrf_token ||
                     document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        const response = await fetch('/api/json', {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token,
            },
        });
        if (response.ok) {
            uploadedFiles.value = await response.json();
        }
    } catch (err) {
        console.error('Failed to load uploaded files:', err);
    }
}

// Load uploaded files on component mount
loadUploadedFiles();
</script>

<template>
    <Head title="JSON Upload" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-6">
            <div class="flex flex-col gap-2">
                <h1 class="text-2xl font-bold">Upload JSON File</h1>
                <p class="text-muted-foreground">
                    Upload a JSON file to start configuring your CSV exports
                </p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Upload Section -->
                <div class="lg:col-span-2">
                    <Card>
                        <CardHeader>
                            <CardTitle class="flex items-center gap-2">
                                <Upload class="h-5 w-5" />
                                File Upload
                            </CardTitle>
                            <CardDescription>
                                Select or drag and drop a JSON file to upload
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <!-- File Drop Zone -->
                            <div
                                :class="[
                                    'border-2 border-dashed rounded-lg p-8 text-center transition-colors',
                                    isDragging
                                        ? 'border-primary bg-primary/5'
                                        : 'border-muted-foreground/25',
                                    file ? 'bg-muted/50' : ''
                                ]"
                                @drop.prevent="handleDrop"
                                @dragover.prevent="handleDragOver"
                                @dragleave="handleDragLeave"
                            >
                                <div v-if="!file" class="space-y-4">
                                    <FileText class="h-12 w-12 mx-auto text-muted-foreground" />
                                    <div>
                                        <p class="text-lg font-medium">
                                            Drop your JSON file here
                                        </p>
                                        <p class="text-sm text-muted-foreground">
                                            or click to browse files
                                        </p>
                                    </div>
                                    <Button type="button" variant="outline" @click="openFileDialog">
                                        Choose File
                                    </Button>
                                </div>

                                <div v-else class="space-y-4">
                                    <FileText class="h-12 w-12 mx-auto text-primary" />
                                    <div>
                                        <p class="text-lg font-medium">{{ file.name }}</p>
                                        <p class="text-sm text-muted-foreground">{{ fileSize }}</p>
                                    </div>
                                    <div class="flex gap-2 justify-center">
                                        <Button
                                            type="button"
                                            @click="uploadFile"
                                            :disabled="!isValidFile || isUploading"
                                        >
                                            {{ isUploading ? 'Uploading...' : 'Upload' }}
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            @click="removeFile"
                                            :disabled="isUploading"
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                </div>
                            </div>

                            <input
                                ref="fileInput"
                                type="file"
                                accept=".json,application/json"
                                class="hidden"
                                @change="handleFileSelect"
                            />

                            <!-- Upload Progress -->
                            <div v-if="isUploading" class="space-y-2">
                                <div class="flex justify-between text-sm">
                                    <span>Uploading...</span>
                                    <span>{{ uploadProgress }}%</span>
                                </div>
                                <div class="w-full bg-muted rounded-full h-2">
                                    <div class="bg-primary h-2 rounded-full transition-all" :style="`width: ${uploadProgress}%`"></div>
                                </div>
                            </div>

                            <!-- Error Message -->
                            <Alert v-if="error" variant="destructive">
                                <AlertCircle class="h-4 w-4" />
                                <AlertDescription>{{ error }}</AlertDescription>
                            </Alert>

                            <!-- File Validation -->
                            <Alert v-if="file && !isValidFile" variant="destructive">
                                <AlertCircle class="h-4 w-4" />
                                <AlertDescription>
                                    Please select a valid JSON file
                                </AlertDescription>
                            </Alert>
                        </CardContent>
                    </Card>
                </div>

                <!-- Recent Files -->
                <div>
                    <Card>
                        <CardHeader>
                            <CardTitle>Recent Files</CardTitle>
                            <CardDescription>
                                Previously uploaded JSON files
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div v-if="uploadedFiles.length === 0" class="text-center py-8">
                                <FileText class="h-8 w-8 mx-auto text-muted-foreground mb-2" />
                                <p class="text-sm text-muted-foreground">No files uploaded yet</p>
                            </div>

                            <div v-else class="space-y-3">
                                <div
                                    v-for="uploadedFile in uploadedFiles"
                                    :key="uploadedFile.id"
                                    class="p-3 border rounded-lg hover:bg-muted/50 transition-colors"
                                >
                                    <div class="flex flex-col gap-1">
                                        <p class="text-sm font-medium truncate">
                                            {{ uploadedFile.original_filename }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ uploadedFile.record_count }} records
                                        </p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ new Date(uploadedFile.created_at).toLocaleDateString() }}
                                        </p>
                                    </div>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        class="mt-2 w-full"
                                        @click="router.visit(route('csv.config.page'), { data: { json_data_id: uploadedFile.id } })"
                                    >
                                        Configure CSV
                                    </Button>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
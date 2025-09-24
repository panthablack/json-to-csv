<?php

namespace App\Http\Controllers;

use App\Models\CsvConfiguration;
use App\Models\JsonData;
use App\Services\CsvBuilderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class CsvExportController extends Controller
{
    public function __construct(
        private CsvBuilderService $csvBuilderService
    ) {}

    public function exportSingle(CsvConfiguration $csvConfiguration): Response
    {
        Gate::authorize('view', $csvConfiguration->jsonData);

        $jsonData = $csvConfiguration->jsonData;
        $data = $jsonData->parsed_data;

        $csvContent = $this->csvBuilderService->generateCsv($data, $csvConfiguration);
        $filename = $this->csvBuilderService->generateFilename($csvConfiguration->name);

        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function exportMultiple(Request $request): Response
    {
        $request->validate([
            'configuration_ids' => 'required|array|min:1',
            'configuration_ids.*' => 'integer|exists:csv_configurations,id'
        ]);

        $configurations = CsvConfiguration::whereIn('id', $request->configuration_ids)
            ->with('jsonData')
            ->get();

        foreach ($configurations as $config) {
            Gate::authorize('view', $config->jsonData);
        }

        $csvFiles = [];

        foreach ($configurations as $config) {
            $jsonData = $config->jsonData;
            $data = $jsonData->parsed_data;
            $csvContent = $this->csvBuilderService->generateCsv($data, $config);

            $csvFiles[] = [
                'name' => $config->name,
                'filename' => $this->csvBuilderService->generateFilename($config->name),
                'content' => $csvContent
            ];
        }

        if (count($csvFiles) === 1) {
            $file = $csvFiles[0];
            return response($file['content'])
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $file['filename'] . '"');
        }

        $zipFilename = 'csv_export_' . date('Y-m-d_H-i-s') . '.zip';
        $zipPath = $this->csvBuilderService->createZipArchive($csvFiles, $zipFilename);

        return response()->download($zipPath, $zipFilename)->deleteFileAfterSend();
    }

    public function generateAndStore(Request $request): JsonResponse
    {
        $request->validate([
            'configuration_id' => 'required|integer|exists:csv_configurations,id',
            'directory' => 'nullable|string'
        ]);

        $configuration = CsvConfiguration::with('jsonData')->findOrFail($request->configuration_id);
        Gate::authorize('view', $configuration->jsonData);

        $jsonData = $configuration->jsonData;
        $data = $jsonData->parsed_data;
        $csvContent = $this->csvBuilderService->generateCsv($data, $configuration);

        $filename = $this->csvBuilderService->generateFilename($configuration->name);
        $directory = $request->input('directory', 'exports');

        $filePath = $this->csvBuilderService->exportToFile($csvContent, $filename, $directory);

        return response()->json([
            'filename' => $filename,
            'path' => $filePath,
            'size' => strlen($csvContent),
            'url' => route('export.download', ['filename' => $filename])
        ]);
    }

    public function download(string $filename): Response
    {
        $filePath = storage_path("app/exports/{$filename}");

        if (!file_exists($filePath)) {
            abort(404, 'File not found');
        }

        return response()->download($filePath, $filename);
    }

    public function quickExport(Request $request): Response
    {
        $request->validate([
            'json_data_id' => 'required|exists:json_data,id',
            'field_mappings' => 'required|array|min:1',
            'transformations' => 'nullable|array',
            'filters' => 'nullable|array',
            'filename' => 'nullable|string|max:255',
            'include_headers' => 'boolean',
            'delimiter' => 'string|max:1',
            'enclosure' => 'string|max:1',
            'escape' => 'string|max:1'
        ]);

        $jsonData = JsonData::findOrFail($request->json_data_id);
        Gate::authorize('view', $jsonData);

        $tempConfig = new CsvConfiguration([
            'field_mappings' => $request->field_mappings,
            'transformations' => $request->transformations,
            'filters' => $request->filters,
            'include_headers' => $request->boolean('include_headers', true),
            'delimiter' => $request->input('delimiter', ','),
            'enclosure' => $request->input('enclosure', '"'),
            'escape' => $request->input('escape', '\\')
        ]);

        $data = $jsonData->parsed_data;
        $csvContent = $this->csvBuilderService->generateCsv($data, $tempConfig);

        $filename = $request->input('filename', 'export_' . date('Y-m-d_H-i-s')) . '.csv';

        return response($csvContent)
            ->header('Content-Type', 'text/csv')
            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
    }

    public function listExports(): JsonResponse
    {
        $exports = collect(Storage::disk('local')->files('exports'))
            ->filter(fn($file) => str_ends_with($file, '.csv') || str_ends_with($file, '.zip'))
            ->map(function ($file) {
                $fullPath = storage_path("app/{$file}");
                return [
                    'filename' => basename($file),
                    'size' => filesize($fullPath),
                    'created_at' => date('Y-m-d H:i:s', filemtime($fullPath)),
                    'download_url' => route('export.download', ['filename' => basename($file)])
                ];
            })
            ->sortByDesc('created_at')
            ->values();

        return response()->json($exports);
    }

    public function deleteExport(string $filename): JsonResponse
    {
        $filePath = "exports/{$filename}";

        if (!Storage::disk('local')->exists($filePath)) {
            return response()->json(['error' => 'File not found'], 404);
        }

        Storage::disk('local')->delete($filePath);

        return response()->json(['message' => 'Export deleted successfully']);
    }

    public function bulkDelete(Request $request): JsonResponse
    {
        $request->validate([
            'filenames' => 'required|array|min:1',
            'filenames.*' => 'string'
        ]);

        $deletedCount = 0;

        foreach ($request->filenames as $filename) {
            $filePath = "exports/{$filename}";
            if (Storage::disk('local')->exists($filePath)) {
                Storage::disk('local')->delete($filePath);
                $deletedCount++;
            }
        }

        return response()->json([
            'message' => "Deleted {$deletedCount} file(s) successfully",
            'deleted_count' => $deletedCount
        ]);
    }
}

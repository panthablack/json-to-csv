<?php

namespace App\Http\Controllers;

use App\Models\JsonData;
use App\Services\JsonParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JsonProcessorController extends Controller
{
    public function __construct(
        private JsonParserService $jsonParserService
    ) {}

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'file' => 'required|file|mimes:json,txt|max:10240'
        ]);

        try {
            $file = $request->file('file');
            $content = file_get_contents($file->getRealPath());

            $parseResult = $this->jsonParserService->parseJsonFile($content);

            if (!$parseResult['success']) {
                throw ValidationException::withMessages([
                    'file' => [$parseResult['error']]
                ]);
            }

            $filename = Str::uuid() . '.json';
            Storage::disk('local')->put("json-uploads/{$filename}", $content);

            $jsonData = JsonData::create([
                'user_id' => auth()->id(),
                'filename' => $filename,
                'original_filename' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'raw_data' => $content,
                'parsed_structure' => $parseResult['structure'],
                'record_count' => $parseResult['record_count'],
                'status' => 'processed'
            ]);

            return response()->json([
                'id' => $jsonData->id,
                'filename' => $jsonData->original_filename,
                'structure' => $jsonData->parsed_structure,
                'record_count' => $jsonData->record_count,
                'available_fields' => $this->jsonParserService->extractFieldPaths($parseResult['data'])
            ]);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to process JSON file: ' . $e->getMessage()
            ], 500);
        }
    }

    public function show(JsonData $json): JsonResponse
    {
        $this->authorize('view', $json);

        return response()->json([
            'id' => $json->id,
            'filename' => $json->original_filename,
            'structure' => $json->parsed_structure,
            'record_count' => $json->record_count,
            'status' => $json->status,
            'available_fields' => $this->jsonParserService->extractFieldPaths($json->parsed_data),
            'created_at' => $json->created_at
        ]);
    }

    public function analyze(JsonData $jsonData): JsonResponse
    {
        $this->authorize('view', $jsonData);

        $data = $jsonData->parsed_data;
        $structure = $this->jsonParserService->analyzeStructure($data);
        $availableFields = $this->jsonParserService->extractFieldPaths($data);

        return response()->json([
            'structure' => $structure,
            'available_fields' => $availableFields,
            'sample_data' => $this->getSampleData($data),
            'record_count' => $this->jsonParserService->countRecords($data)
        ]);
    }

    public function index(): JsonResponse
    {
        $jsonFiles = JsonData::where('user_id', auth()->id())
            ->orderBy('created_at', 'desc')
            ->get(['id', 'original_filename', 'record_count', 'status', 'created_at']);

        return response()->json($jsonFiles);
    }

    public function destroy(JsonData $json): JsonResponse
    {
        // Debug logging
        \Log::info('Delete request details:', [
            'authenticated_user_id' => auth()->id(),
            'authenticated_user_email' => auth()->user()->email ?? 'none',
            'json_data_id' => $json->id,
            'json_data_user_id' => $json->user_id,
            'json_data_filename' => $json->original_filename,
        ]);

        $this->authorize('delete', $json);

        Storage::disk('local')->delete("json-uploads/{$json->filename}");
        $json->delete();

        return response()->json(['message' => 'JSON file deleted successfully']);
    }

    private function getSampleData($data, int $limit = 3): array
    {
        if (is_array($data) && !$this->jsonParserService->isAssociativeArray($data)) {
            return array_slice($data, 0, $limit);
        }

        return [$data];
    }
}

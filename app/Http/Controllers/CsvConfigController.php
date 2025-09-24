<?php

namespace App\Http\Controllers;

use App\Models\CsvConfiguration;
use App\Models\JsonData;
use App\Services\CsvBuilderService;
use App\Services\JsonParserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CsvConfigController extends Controller
{
    public function __construct(
        private CsvBuilderService $csvBuilderService,
        private JsonParserService $jsonParserService
    ) {}

    public function index(): JsonResponse
    {
        $configurations = CsvConfiguration::where('user_id', auth()->id())
            ->with('jsonData:id,original_filename')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'json_data_id', 'name', 'description', 'created_at']);

        return response()->json($configurations);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'json_data_id' => 'required|exists:json_data,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'field_mappings' => 'required|array|min:1',
            'column_order' => 'nullable|array',
            'filters' => 'nullable|array',
            'transformations' => 'nullable|array',
            'include_headers' => 'boolean',
            'delimiter' => 'string|max:1',
            'enclosure' => 'string|max:1',
            'escape' => 'string|max:1'
        ]);

        $jsonData = JsonData::findOrFail($request->json_data_id);
        Gate::authorize('view', $jsonData);

        $availableFields = $this->jsonParserService->extractFieldPaths($jsonData->parsed_data);
        $validationErrors = $this->csvBuilderService->validateConfiguration(
            $request->field_mappings,
            $availableFields
        );

        if (!empty($validationErrors)) {
            return response()->json([
                'errors' => ['field_mappings' => $validationErrors]
            ], 422);
        }

        $configuration = CsvConfiguration::create([
            'user_id' => auth()->id(),
            'json_data_id' => $request->json_data_id,
            'name' => $request->name,
            'description' => $request->description,
            'field_mappings' => $request->field_mappings,
            'column_order' => $request->column_order,
            'filters' => $request->filters,
            'transformations' => $request->transformations,
            'include_headers' => $request->boolean('include_headers', true),
            'delimiter' => $request->input('delimiter', ','),
            'enclosure' => $request->input('enclosure', '"'),
            'escape' => $request->input('escape', '\\')
        ]);

        return response()->json($configuration, 201);
    }

    public function show(CsvConfiguration $csvConfiguration): JsonResponse
    {
        Gate::authorize('view', $csvConfiguration->jsonData);

        $csvConfiguration->load('jsonData:id,original_filename,record_count');

        return response()->json($csvConfiguration);
    }

    public function update(Request $request, CsvConfiguration $csvConfiguration): JsonResponse
    {
        Gate::authorize('update', $csvConfiguration);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'field_mappings' => 'required|array|min:1',
            'column_order' => 'nullable|array',
            'filters' => 'nullable|array',
            'transformations' => 'nullable|array',
            'include_headers' => 'boolean',
            'delimiter' => 'string|max:1',
            'enclosure' => 'string|max:1',
            'escape' => 'string|max:1'
        ]);

        $jsonData = $csvConfiguration->jsonData;
        $availableFields = $this->jsonParserService->extractFieldPaths($jsonData->parsed_data);
        $validationErrors = $this->csvBuilderService->validateConfiguration(
            $request->field_mappings,
            $availableFields
        );

        if (!empty($validationErrors)) {
            return response()->json([
                'errors' => ['field_mappings' => $validationErrors]
            ], 422);
        }

        $csvConfiguration->update([
            'name' => $request->name,
            'description' => $request->description,
            'field_mappings' => $request->field_mappings,
            'column_order' => $request->column_order,
            'filters' => $request->filters,
            'transformations' => $request->transformations,
            'include_headers' => $request->boolean('include_headers', true),
            'delimiter' => $request->input('delimiter', ','),
            'enclosure' => $request->input('enclosure', '"'),
            'escape' => $request->input('escape', '\\')
        ]);

        return response()->json($csvConfiguration);
    }

    public function destroy(CsvConfiguration $csvConfiguration): JsonResponse
    {
        Gate::authorize('delete', $csvConfiguration);

        $csvConfiguration->delete();

        return response()->json(['message' => 'Configuration deleted successfully']);
    }

    public function preview(Request $request): JsonResponse
    {
        $request->validate([
            'json_data_id' => 'required|exists:json_data,id',
            'field_mappings' => 'required|array|min:1',
            'transformations' => 'nullable|array',
            'filters' => 'nullable|array',
            'limit' => 'integer|min:1|max:20'
        ]);

        $jsonData = JsonData::findOrFail($request->json_data_id);
        Gate::authorize('view', $jsonData);

        $data = $jsonData->parsed_data;
        $limit = $request->integer('limit', 5);

        $preview = $this->csvBuilderService->previewCsv(
            $data,
            $request->field_mappings,
            $request->transformations ?? [],
            $limit
        );

        return response()->json($preview);
    }

    public function suggest(JsonData $jsonData): JsonResponse
    {
        Gate::authorize('view', $jsonData);

        $availableFields = $this->jsonParserService->extractFieldPaths($jsonData->parsed_data);
        $suggestions = $this->csvBuilderService->suggestFieldMappings($availableFields);

        return response()->json([
            'available_fields' => $availableFields,
            'suggested_mappings' => $suggestions,
            'json_structure' => $jsonData->parsed_structure
        ]);
    }

    public function analyze(CsvConfiguration $csvConfiguration): JsonResponse
    {
        Gate::authorize('view', $csvConfiguration->jsonData);

        $jsonData = $csvConfiguration->jsonData;
        $analysis = $this->csvBuilderService->analyzeCsvStructure(
            $jsonData->parsed_data,
            $csvConfiguration->field_mappings
        );

        return response()->json($analysis);
    }

    public function duplicate(CsvConfiguration $csvConfiguration): JsonResponse
    {
        Gate::authorize('view', $csvConfiguration->jsonData);

        $newConfiguration = $csvConfiguration->replicate();
        $newConfiguration->name = $csvConfiguration->name . ' (Copy)';
        $newConfiguration->user_id = auth()->id();
        $newConfiguration->save();

        return response()->json($newConfiguration, 201);
    }

    // New methods for nested routes under /json-data/{jsonData}/csv-config

    public function indexForJson(JsonData $jsonData): JsonResponse
    {
        Gate::authorize('view', $jsonData);

        $configurations = CsvConfiguration::where('user_id', auth()->id())
            ->where('json_data_id', $jsonData->id)
            ->with('jsonData:id,original_filename')
            ->orderBy('created_at', 'desc')
            ->get(['id', 'json_data_id', 'name', 'description', 'created_at']);

        return response()->json($configurations);
    }

    public function storeForJson(Request $request, JsonData $jsonData): JsonResponse
    {
        Gate::authorize('view', $jsonData);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'field_mappings' => 'required|array|min:1',
            'column_order' => 'nullable|array',
            'filters' => 'nullable|array',
            'transformations' => 'nullable|array',
            'include_headers' => 'boolean',
            'delimiter' => 'string|max:1',
            'enclosure' => 'string|max:1',
            'escape' => 'string|max:1'
        ]);

        $availableFields = $this->jsonParserService->extractFieldPaths($jsonData->parsed_data);
        $validationErrors = $this->csvBuilderService->validateConfiguration(
            $request->field_mappings,
            $availableFields
        );

        if (!empty($validationErrors)) {
            return response()->json([
                'errors' => ['field_mappings' => $validationErrors]
            ], 422);
        }

        $configuration = CsvConfiguration::create([
            'user_id' => auth()->id(),
            'json_data_id' => $jsonData->id,
            'name' => $request->name,
            'description' => $request->description,
            'field_mappings' => $request->field_mappings,
            'column_order' => $request->column_order,
            'filters' => $request->filters,
            'transformations' => $request->transformations,
            'include_headers' => $request->boolean('include_headers', true),
            'delimiter' => $request->input('delimiter', ','),
            'enclosure' => $request->input('enclosure', '"'),
            'escape' => $request->input('escape', '\\')
        ]);

        return response()->json($configuration, 201);
    }

    public function previewForJson(Request $request, JsonData $jsonData): JsonResponse
    {
        Gate::authorize('view', $jsonData);

        $request->validate([
            'field_mappings' => 'required|array|min:1',
            'transformations' => 'nullable|array',
            'filters' => 'nullable|array',
            'limit' => 'integer|min:1|max:20'
        ]);

        $data = $jsonData->parsed_data;
        $limit = $request->integer('limit', 5);

        $preview = $this->csvBuilderService->previewCsv(
            $data,
            $request->field_mappings,
            $request->transformations ?? [],
            $limit
        );

        return response()->json($preview);
    }
}

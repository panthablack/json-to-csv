<?php

namespace App\Services;

use App\Models\CsvConfiguration;
use League\Csv\Writer;
use League\Csv\CharsetConverter;

class CsvBuilderService
{
    public function __construct(
        private DataTransformerService $dataTransformerService
    ) {}

    public function generateCsv(array $data, CsvConfiguration $config): string
    {
        $csv = Writer::createFromString();

        $csv->setDelimiter($config->delimiter);
        $csv->setEnclosure($config->enclosure);
        $csv->setEscape($config->escape);

        if ($config->hasFilters()) {
            $data = $this->dataTransformerService->applyFilters($data, $config->filters);
        }

        $transformedData = $this->dataTransformerService->transformData(
            $data,
            $config->field_mappings,
            $config->transformations ?? []
        );

        if ($config->column_order) {
            $transformedData = $this->dataTransformerService->reorderColumns($transformedData, $config->column_order);
        }

        if ($config->include_headers && !empty($transformedData)) {
            $headers = array_keys($transformedData[0]);
            $csv->insertOne($headers);
        }

        foreach ($transformedData as $record) {
            $csv->insertOne(array_values($record));
        }

        return $csv->toString();
    }

    public function generateMultipleCsvs(array $data, array $configurations): array
    {
        $results = [];

        foreach ($configurations as $config) {
            $csvContent = $this->generateCsv($data, $config);
            $results[] = [
                'name' => $config->name,
                'filename' => $this->generateFilename($config->name),
                'content' => $csvContent,
                'size' => strlen($csvContent)
            ];
        }

        return $results;
    }

    public function previewCsv(array $data, array $fieldMappings, array $transformations = [], int $limit = 5): array
    {
        $limitedData = array_slice($data, 0, $limit);

        $transformedData = $this->dataTransformerService->transformData(
            $limitedData,
            $fieldMappings,
            $transformations
        );

        return [
            'headers' => !empty($transformedData) ? array_keys($transformedData[0]) : [],
            'rows' => array_map('array_values', $transformedData),
            'total_records' => count($data),
            'preview_records' => count($transformedData)
        ];
    }

    public function validateConfiguration(array $fieldMappings, array $availableFields): array
    {
        $errors = [];

        foreach ($fieldMappings as $csvColumn => $sourceField) {
            if (empty($csvColumn)) {
                $errors[] = "CSV column name cannot be empty";
                continue;
            }

            if (empty($sourceField)) {
                $errors[] = "Source field for column '$csvColumn' cannot be empty";
                continue;
            }

            if (!in_array($sourceField, $availableFields)) {
                $errors[] = "Source field '$sourceField' for column '$csvColumn' does not exist in the JSON data";
            }
        }

        return $errors;
    }

    public function suggestFieldMappings(array $availableFields): array
    {
        $suggestions = [];

        foreach ($availableFields as $field) {
            $csvColumnName = $this->generateCsvColumnName($field);
            $suggestions[$csvColumnName] = $field;
        }

        return $suggestions;
    }

    public function exportToFile(string $csvContent, string $filename, string $directory = 'exports'): string
    {
        $fullPath = storage_path("app/{$directory}/{$filename}");

        if (!is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        file_put_contents($fullPath, $csvContent);

        return $fullPath;
    }

    public function createZipArchive(array $csvFiles, string $zipFilename): string
    {
        $zipPath = storage_path("app/exports/{$zipFilename}");

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) !== TRUE) {
            throw new \Exception("Cannot create ZIP file: {$zipPath}");
        }

        foreach ($csvFiles as $file) {
            $zip->addFromString($file['filename'], $file['content']);
        }

        $zip->close();

        return $zipPath;
    }

    public function generateFilename(string $name): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9\-_]/', '_', $name);
        $timestamp = date('Y-m-d_H-i-s');
        return "{$sanitized}_{$timestamp}.csv";
    }

    private function generateCsvColumnName(string $fieldPath): string
    {
        $parts = explode('.', $fieldPath);
        $lastPart = end($parts);

        return ucwords(str_replace(['_', '-'], ' ', $lastPart));
    }

    public function analyzeCsvStructure(array $data, array $fieldMappings): array
    {
        $analysis = [
            'total_records' => count($data),
            'columns' => [],
            'data_types' => [],
            'sample_values' => []
        ];

        if (empty($data)) {
            return $analysis;
        }

        $transformedData = $this->dataTransformerService->transformData($data, $fieldMappings);

        if (!empty($transformedData)) {
            $firstRecord = $transformedData[0];

            foreach ($firstRecord as $column => $value) {
                $analysis['columns'][] = $column;
                $analysis['data_types'][$column] = $this->detectDataType($transformedData, $column);
                $analysis['sample_values'][$column] = $this->getSampleValues($transformedData, $column, 3);
            }
        }

        return $analysis;
    }

    private function detectDataType(array $data, string $column): string
    {
        $values = array_column($data, $column);
        $nonEmptyValues = array_filter($values, fn($v) => $v !== null && $v !== '');

        if (empty($nonEmptyValues)) {
            return 'empty';
        }

        $sample = array_slice($nonEmptyValues, 0, 10);

        $isNumeric = array_reduce($sample, fn($carry, $val) => $carry && is_numeric($val), true);
        if ($isNumeric) {
            $hasDecimals = array_reduce($sample, fn($carry, $val) => $carry || (is_float($val) || strpos($val, '.') !== false), false);
            return $hasDecimals ? 'decimal' : 'integer';
        }

        $isBoolean = array_reduce($sample, fn($carry, $val) => $carry && is_bool($val), true);
        if ($isBoolean) {
            return 'boolean';
        }

        $isDate = array_reduce($sample, function($carry, $val) {
            return $carry && (strtotime($val) !== false);
        }, true);
        if ($isDate) {
            return 'date';
        }

        return 'string';
    }

    private function getSampleValues(array $data, string $column, int $limit): array
    {
        $values = array_column($data, $column);
        $uniqueValues = array_unique($values);
        return array_slice($uniqueValues, 0, $limit);
    }
}
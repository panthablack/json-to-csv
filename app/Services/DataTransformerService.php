<?php

namespace App\Services;

use Exception;

class DataTransformerService
{
    public function __construct(
        private JsonParserService $jsonParserService
    ) {
    }

    public function transformData(array $data, array $fieldMappings, array $transformations = []): array
    {
        $transformedData = [];

        foreach ($data as $record) {
            $transformedRecord = [];

            foreach ($fieldMappings as $csvColumn => $sourceField) {
                $value = $this->extractValue($record, $sourceField);

                if (isset($transformations[$csvColumn])) {
                    $value = $this->applyTransformation($value, $transformations[$csvColumn], $record);
                }

                $transformedRecord[$csvColumn] = $value;
            }

            $transformedData[] = $transformedRecord;
        }

        return $transformedData;
    }

    public function extractValue($record, string $fieldPath)
    {
        if (strpos($fieldPath, '.') !== false) {
            return $this->jsonParserService->getValueByPath($record, $fieldPath);
        }

        return $record[$fieldPath] ?? null;
    }

    public function applyTransformation($value, array $transformation, $fullRecord = null)
    {
        $type = $transformation['type'] ?? 'none';

        switch ($type) {
            case 'callback':
                return $this->executeCallback($transformation['function'], $value, $fullRecord);

            case 'format_date':
                return $this->formatDate($value, $transformation['format'] ?? 'Y-m-d');

            case 'format_number':
                return $this->formatNumber($value, $transformation['decimals'] ?? 2);

            case 'string_case':
                return $this->transformStringCase($value, $transformation['case'] ?? 'lower');

            case 'concat':
                return $this->concatenateFields($fullRecord, $transformation['fields'], $transformation['separator'] ?? ' ');

            case 'conditional':
                return $this->conditionalValue($value, $transformation['conditions']);

            case 'replace':
                return $this->replaceValue($value, $transformation['search'], $transformation['replace'] ?? '');

            case 'truncate':
                return $this->truncateString($value, $transformation['length'] ?? 50);

            default:
                return $value;
        }
    }

    public function executeCallback(string $jsFunction, $value, $fullRecord = null)
    {
        try {
            $phpFunction = $this->convertJsToPhp($jsFunction);
            return $phpFunction($value, $fullRecord);
        } catch (Exception $e) {
            return "ERROR: " . $e->getMessage();
        }
    }

    public function formatDate($value, string $format): string
    {
        if (empty($value)) {
            return '';
        }

        try {
            $date = new \DateTime($value);
            return $date->format($format);
        } catch (Exception $e) {
            return (string) $value;
        }
    }

    public function formatNumber($value, int $decimals): string
    {
        if (!is_numeric($value)) {
            return (string) $value;
        }

        return number_format((float) $value, $decimals);
    }

    public function transformStringCase($value, string $case): string
    {
        $stringValue = (string) $value;

        return match ($case) {
            'upper' => strtoupper($stringValue),
            'lower' => strtolower($stringValue),
            'title' => ucwords($stringValue),
            'sentence' => ucfirst(strtolower($stringValue)),
            default => $stringValue,
        };
    }

    public function concatenateFields($record, array $fieldPaths, string $separator = ' '): string
    {
        $values = [];

        foreach ($fieldPaths as $fieldPath) {
            $value = $this->extractValue($record, $fieldPath);
            if (!empty($value)) {
                $values[] = $value;
            }
        }

        return implode($separator, $values);
    }

    public function conditionalValue($value, array $conditions)
    {
        foreach ($conditions as $condition) {
            $operator = $condition['operator'] ?? '==';
            $compareValue = $condition['value'] ?? null;
            $returnValue = $condition['return'] ?? $value;

            if ($this->evaluateCondition($value, $operator, $compareValue)) {
                return $returnValue;
            }
        }

        return $conditions['default'] ?? $value;
    }

    public function replaceValue($value, $search, $replace): string
    {
        return str_replace($search, $replace, (string) $value);
    }

    public function truncateString($value, int $length): string
    {
        $stringValue = (string) $value;

        if (strlen($stringValue) <= $length) {
            return $stringValue;
        }

        return substr($stringValue, 0, $length) . '...';
    }

    private function evaluateCondition($value, string $operator, $compareValue): bool
    {
        return match ($operator) {
            '==' => $value == $compareValue,
            '!=' => $value != $compareValue,
            '>' => $value > $compareValue,
            '>=' => $value >= $compareValue,
            '<' => $value < $compareValue,
            '<=' => $value <= $compareValue,
            'contains' => str_contains((string) $value, (string) $compareValue),
            'starts_with' => str_starts_with((string) $value, (string) $compareValue),
            'ends_with' => str_ends_with((string) $value, (string) $compareValue),
            'empty' => empty($value),
            'not_empty' => !empty($value),
            default => false,
        };
    }

    private function convertJsToPhp(string $jsFunction): callable
    {
        if (preg_match('/return\s+(.+);?\s*$/', $jsFunction, $matches)) {
            $expression = trim($matches[1], ';');

            return function ($value, $record) use ($expression) {
                $data = $record ?? [];
                $data['value'] = $value;

                return eval("return $expression;");
            };
        }

        throw new Exception('Invalid callback function format');
    }

    public function applyFilters(array $data, array $filters): array
    {
        if (empty($filters)) {
            return $data;
        }

        return array_filter($data, function ($record) use ($filters) {
            foreach ($filters as $filter) {
                $fieldPath = $filter['field'] ?? '';
                $operator = $filter['operator'] ?? '==';
                $value = $filter['value'] ?? null;

                $recordValue = $this->extractValue($record, $fieldPath);

                if (!$this->evaluateCondition($recordValue, $operator, $value)) {
                    return false;
                }
            }

            return true;
        });
    }

    public function reorderColumns(array $data, array $columnOrder): array
    {
        if (empty($columnOrder) || empty($data)) {
            return $data;
        }

        return array_map(function ($record) use ($columnOrder) {
            $reorderedRecord = [];

            foreach ($columnOrder as $column) {
                if (isset($record[$column])) {
                    $reorderedRecord[$column] = $record[$column];
                }
            }

            foreach ($record as $key => $value) {
                if (!in_array($key, $columnOrder)) {
                    $reorderedRecord[$key] = $value;
                }
            }

            return $reorderedRecord;
        }, $data);
    }
}

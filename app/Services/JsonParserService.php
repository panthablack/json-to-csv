<?php

namespace App\Services;

use Exception;

class JsonParserService
{
    public function parseJsonFile(string $content): array
    {
        try {
            $data = json_decode($content, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON: ' . json_last_error_msg());
            }

            return [
                'success' => true,
                'data' => $data,
                'structure' => $this->analyzeStructure($data),
                'record_count' => $this->countRecords($data)
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function analyzeStructure($data, int $depth = 0, int $maxDepth = 5): array
    {
        if ($depth > $maxDepth) {
            return ['type' => 'max_depth_exceeded'];
        }

        if (is_array($data)) {
            if (empty($data)) {
                return ['type' => 'empty_array'];
            }

            if ($this->isAssociativeArray($data)) {
                $structure = ['type' => 'object', 'properties' => []];
                foreach ($data as $key => $value) {
                    $structure['properties'][$key] = $this->analyzeStructure($value, $depth + 1, $maxDepth);
                }
                return $structure;
            } else {
                $firstElement = $data[0] ?? null;
                return [
                    'type' => 'array',
                    'length' => count($data),
                    'element_type' => $firstElement !== null ? $this->analyzeStructure($firstElement, $depth + 1, $maxDepth) : null
                ];
            }
        }

        return ['type' => gettype($data)];
    }

    public function extractFieldPaths($data, string $prefix = ''): array
    {
        $paths = [];

        if (is_array($data)) {
            if ($this->isAssociativeArray($data)) {
                foreach ($data as $key => $value) {
                    $currentPath = $prefix ? "$prefix.$key" : $key;
                    $paths[] = $currentPath;

                    if (is_array($value)) {
                        $paths = array_merge($paths, $this->extractFieldPaths($value, $currentPath));
                    }
                }
            } else {
                foreach ($data as $index => $item) {
                    if (is_array($item)) {
                        $itemPaths = $this->extractFieldPaths($item, $prefix);
                        $paths = array_merge($paths, $itemPaths);
                    }
                }
            }
        }

        return array_unique($paths);
    }

    public function getValueByPath($data, string $path)
    {
        $keys = explode('.', $path);
        $current = $data;

        foreach ($keys as $key) {
            if (is_array($current) && isset($current[$key])) {
                $current = $current[$key];
            } else {
                return null;
            }
        }

        return $current;
    }

    public function isAssociativeArray(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }

    public function countRecords($data): int
    {
        if (is_array($data)) {
            if ($this->isAssociativeArray($data)) {
                return 1;
            } else {
                return count($data);
            }
        }

        return 1;
    }
}
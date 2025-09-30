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

    /**
     * Check if two JSON structures are compatible
     * Compatible means the new structure has all the same paths as the old structure
     * New structure can have additional paths, but cannot be missing existing paths
     */
    public function areStructuresCompatible(array $oldStructure, array $newStructure): array
    {
        $oldPaths = $this->extractStructurePaths($oldStructure);
        $newPaths = $this->extractStructurePaths($newStructure);

        $missingPaths = array_diff($oldPaths, $newPaths);
        $addedPaths = array_diff($newPaths, $oldPaths);

        $isCompatible = empty($missingPaths);

        return [
            'compatible' => $isCompatible,
            'missing_paths' => array_values($missingPaths),
            'added_paths' => array_values($addedPaths),
            'message' => $isCompatible
                ? 'Structures are compatible'
                : 'New structure is missing required paths: ' . implode(', ', $missingPaths)
        ];
    }

    /**
     * Extract all possible field paths from a structure
     */
    private function extractStructurePaths(array $structure, string $prefix = ''): array
    {
        $paths = [];

        if (isset($structure['type'])) {
            if ($structure['type'] === 'object' && isset($structure['properties'])) {
                foreach ($structure['properties'] as $key => $value) {
                    $currentPath = $prefix ? "$prefix.$key" : $key;
                    $paths[] = $currentPath;

                    if (is_array($value)) {
                        $subPaths = $this->extractStructurePaths($value, $currentPath);
                        $paths = array_merge($paths, $subPaths);
                    }
                }
            } elseif ($structure['type'] === 'array' && isset($structure['element_type'])) {
                // For arrays, analyze the element type structure
                $subPaths = $this->extractStructurePaths($structure['element_type'], $prefix);
                $paths = array_merge($paths, $subPaths);
            }
        }

        return array_unique($paths);
    }
}

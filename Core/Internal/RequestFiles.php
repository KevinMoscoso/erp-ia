<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2025 ERPIA Development Team
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace ERPIA\Core\Internal;

use ERPIA\Core\UploadedFile;

final class RequestFiles
{
    /** @var array */
    private $fileCollection = [];

    public function __construct(array $inputData = [])
    {
        $processedFiles = [];
        
        foreach ($inputData as $fieldName => $fieldData) {
            if (!isset($fieldData['size'])) {
                continue;
            }
            
            if (is_array($fieldData['size'])) {
                $processedFiles[$fieldName] = $this->restructureFileArray($fieldData);
            } elseif ($fieldData['size'] > 0) {
                $processedFiles[$fieldName] = $fieldData;
            }
        }

        foreach ($processedFiles as $fieldName => $fileInfo) {
            if (isset($fileInfo[0]) && is_array($fileInfo[0])) {
                foreach ($fileInfo as $individualFile) {
                    $this->fileCollection[$fieldName][] = new UploadedFile($individualFile);
                }
            } else {
                $this->fileCollection[$fieldName] = new UploadedFile($fileInfo);
            }
        }
    }

    /**
     * @param string ...$fieldNames
     * @return UploadedFile[]
     */
    public function getAll(string ...$fieldNames): array
    {
        if (empty($fieldNames)) {
            return $this->fileCollection;
        }

        $resultSet = [];
        foreach ($fieldNames as $field) {
            if ($this->contains($field)) {
                $resultSet[$field] = $this->fileCollection[$field];
            }
        }
        return $resultSet;
    }

    public function getSingle(string $fieldName): ?UploadedFile
    {
        if ($this->contains($fieldName) && $this->fileCollection[$fieldName] instanceof UploadedFile) {
            return $this->fileCollection[$fieldName];
        }

        return null;
    }

    /** @return UploadedFile[] */
    public function getMultiple(string $fieldName): array
    {
        if ($this->contains($fieldName) && is_array($this->fileCollection[$fieldName])) {
            return $this->fileCollection[$fieldName];
        }

        return [];
    }

    public function contains(string $fieldName): bool
    {
        return isset($this->fileCollection[$fieldName]);
    }

    public function isAbsent(string $fieldName): bool
    {
        return !isset($this->fileCollection[$fieldName]);
    }

    public function remove(string $fieldName): void
    {
        unset($this->fileCollection[$fieldName]);
    }

    public function assign(string $fieldName, UploadedFile $fileInstance): void
    {
        $this->fileCollection[$fieldName] = $fileInstance;
    }

    private function restructureFileArray($fileData): array
    {
        $restructured = [];
        $fileCount = count($fileData['name']);
        $fileProperties = array_keys($fileData);

        for ($index = 0; $index < $fileCount; $index++) {
            if ($fileData['size'][$index] > 0) {
                foreach ($fileProperties as $property) {
                    $restructured[$index][$property] = $fileData[$property][$index];
                }
            }
        }

        return $restructured;
    }

    public function all(string ...$keys): array
    {
        return $this->getAll(...$keys);
    }

    public function get(string $key): ?UploadedFile
    {
        return $this->getSingle($key);
    }

    public function getArray(string $key): array
    {
        return $this->getMultiple($key);
    }

    public function has(string $key): bool
    {
        return $this->contains($key);
    }

    public function isMissing(string $key): bool
    {
        return $this->isAbsent($key);
    }

    public function set(string $key, UploadedFile $value): void
    {
        $this->assign($key, $value);
    }
}
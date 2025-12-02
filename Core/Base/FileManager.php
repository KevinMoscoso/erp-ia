<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2017-2025 ERPIA Contributors
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

namespace ERPIA\Core\Base;

/**
 * Class to manage file and folder operations
 *
 * @author ERPIA Contributors
 */
class FileManager
{
    /**
     * Default folder creation permissions
     */
    const FOLDER_PERMISSIONS = 0755;

    /**
     * Default excluded folders in directory scans
     */
    const EXCLUDED_ITEMS = ['.', '..', '.DS_Store', '.well-known'];

    /**
     * Create a directory with specified permissions
     */
    public static function createDirectory(string $path, bool $recursive = false, int $permissions = self::FOLDER_PERMISSIONS): bool
    {
        if (!file_exists($path) && !@mkdir($path, $permissions, $recursive) && !is_dir($path)) {
            return false;
        }

        return true;
    }

    /**
     * Recursively delete a directory and its contents
     */
    public static function deleteRecursive(string $path): bool
    {
        if (!file_exists($path)) {
            return true;
        }

        $items = is_dir($path) ? static::scanDirectory($path, false, ['.', '..']) : [];
        foreach ($items as $item) {
            $fullPath = $path . DIRECTORY_SEPARATOR . $item;
            is_dir($fullPath) ? static::deleteRecursive($fullPath) : unlink($fullPath);
        }

        return is_dir($path) ? rmdir($path) : unlink($path);
    }

    /**
     * Extract content between markers in a file
     */
    public static function extractMarkerContent(string $filename, string $marker): array
    {
        $content = [];
        if (!file_exists($filename)) {
            return $content;
        }

        $lines = explode("\n", file_get_contents($filename));
        $active = false;

        foreach ($lines as $line) {
            if (strpos($line, '# END ' . $marker) !== false) {
                $active = false;
            }
            if ($active) {
                $content[] = $line;
            }
            if (strpos($line, '# BEGIN ' . $marker) !== false) {
                $active = true;
            }
        }

        return $content;
    }

    /**
     * Insert content between markers in a file
     */
    public static function insertWithMarkers(array $content, string $filename, string $marker): bool
    {
        if (!file_exists($filename)) {
            if (!is_writable(dirname($filename))) {
                return false;
            }
            if (!touch($filename)) {
                return false;
            }
        } elseif (!is_writable($filename)) {
            return false;
        }

        $startMarker = '# BEGIN ' . $marker;
        $endMarker = '# END ' . $marker;
        $fileHandle = fopen($filename, 'rb+');
        if (!$fileHandle) {
            return false;
        }

        flock($fileHandle, LOCK_EX);
        $fileLines = [];
        while (!feof($fileHandle)) {
            $fileLines[] = rtrim(fgets($fileHandle), "\r\n");
        }

        $beforeLines = $afterLines = $existingLines = [];
        $markerFound = $endMarkerFound = false;

        foreach ($fileLines as $line) {
            if (!$markerFound && strpos($line, $startMarker) !== false) {
                $markerFound = true;
                continue;
            }
            if (!$endMarkerFound && strpos($line, $endMarker) !== false) {
                $endMarkerFound = true;
                continue;
            }
            if (!$markerFound) {
                $beforeLines[] = $line;
            } elseif ($markerFound && $endMarkerFound) {
                $afterLines[] = $line;
            } else {
                $existingLines[] = $line;
            }
        }

        if ($existingLines === $content) {
            flock($fileHandle, LOCK_UN);
            fclose($fileHandle);
            return true;
        }

        if (empty(array_diff($content, $beforeLines))) {
            $beforeLines = [];
        }

        $newContent = implode(
            PHP_EOL,
            array_merge(
                $beforeLines,
                [$startMarker],
                $content,
                [$endMarker],
                $afterLines
            )
        );

        fseek($fileHandle, 0);
        $written = fwrite($fileHandle, $newContent);
        if ($written) {
            ftruncate($fileHandle, ftell($fileHandle));
        }
        fflush($fileHandle);
        flock($fileHandle, LOCK_UN);
        fclose($fileHandle);
        return (bool) $written;
    }

    /**
     * Get list of non-writable directories
     */
    public static function getNonWritableDirectories(): array
    {
        $nonWritable = [];
        foreach (static::scanDirectory(ERPIA_BASE_PATH, true) as $directory) {
            if (is_dir($directory) && !is_writable($directory)) {
                $nonWritable[] = $directory;
            }
        }

        return $nonWritable;
    }

    /**
     * Recursively copy directory contents
     */
    public static function copyRecursive(string $source, string $destination): bool
    {
        $directoryHandle = opendir($source);
        if (!$directoryHandle) {
            return false;
        }

        if (!static::createDirectory($destination)) {
            closedir($directoryHandle);
            return false;
        }

        while (($item = readdir($directoryHandle)) !== false) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $sourcePath = $source . DIRECTORY_SEPARATOR . $item;
            $destinationPath = $destination . DIRECTORY_SEPARATOR . $item;

            if (is_dir($sourcePath)) {
                static::copyRecursive($sourcePath, $destinationPath);
            } else {
                copy($sourcePath, $destinationPath);
            }
        }

        closedir($directoryHandle);
        return true;
    }

    /**
     * Scan directory contents with optional recursion
     */
    public static function scanDirectory(string $path, bool $recursive = false, array $exclusions = self::EXCLUDED_ITEMS): array
    {
        $directoryContents = scandir($path, SCANDIR_SORT_ASCENDING);
        if (!is_array($directoryContents)) {
            return [];
        }

        $filteredItems = array_diff($directoryContents, $exclusions);
        if (!$recursive) {
            return array_values($filteredItems);
        }

        $result = [];
        foreach ($filteredItems as $item) {
            $itemPath = $path . DIRECTORY_SEPARATOR . $item;
            if (is_file($itemPath)) {
                $result[] = $item;
                continue;
            }
            $result[] = $item;
            foreach (static::scanDirectory($itemPath, true) as $nestedItem) {
                $result[] = $item . DIRECTORY_SEPARATOR . $nestedItem;
            }
        }

        return $result;
    }
}
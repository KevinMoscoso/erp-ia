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

use Closure;
use ERPIA\Core\Cache;

/**
 * Auxiliary class for fluent pattern with in-memory caching
 */
class CacheWithMemory
{
    private static array $localStorage = [];

    public static function clear(): void
    {
        self::$localStorage = [];

        Cache::clear();
    }

    public function delete(string $identifier): void
    {
        // Remove from local memory
        unset(self::$localStorage[$identifier]);

        // Also remove from persistent cache
        Cache::delete($identifier);
    }

    public static function deleteMulti(string $keyPrefix): void
    {
        // Remove from memory all items starting with the prefix
        foreach (self::$localStorage as $itemKey => $storedItem) {
            $prefixLength = strlen($keyPrefix);
            if (substr($itemKey, 0, $prefixLength) === $keyPrefix) {
                unset(self::$localStorage[$itemKey]);
            }
        }

        // Also remove from persistent cache
        Cache::deleteMulti($keyPrefix);
    }

    public static function expire(): void
    {
        $currentTimestamp = time();
        foreach (self::$localStorage as $itemKey => $storedItem) {
            if ($storedItem['expiration'] < $currentTimestamp) {
                unset(self::$localStorage[$itemKey]);
            }
        }

        Cache::expire();
    }

    public function get(string $identifier)
    {
        // First try to get from local memory
        if (isset(self::$localStorage[$identifier])) {
            $storedItem = self::$localStorage[$identifier];
            // Check if not expired
            if ($storedItem['expiration'] >= time()) {
                return $storedItem['content'];
            }
            // If expired, remove from memory
            unset(self::$localStorage[$identifier]);
        }

        // If not in memory or expired, try from persistent cache
        return Cache::get($identifier);
    }

    public function remember(string $identifier, Closure $generator)
    {
        if (!is_null($cachedValue = $this->get($identifier))) {
            return $cachedValue;
        }

        $generatedValue = $generator();
        $this->store($identifier, $generatedValue);
        return $generatedValue;
    }

    public function store(string $identifier, $content): void
    {
        // Store in local memory
        self::$localStorage[$identifier] = [
            'content' => $content,
            'expiration' => time() + Cache::DEFAULT_TTL
        ];

        // Also store in persistent cache
        Cache::store($identifier, $content);
    }
    
    public function set(string $identifier, $content): void
    {
        $this->store($identifier, $content);
    }
}
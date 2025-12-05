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

use ERPIA\Core\Tools;
use ERPIA\Core\Validator;

class Headers
{
    /** @var array */
    private $headersData;

    public function __construct(array $data)
    {
        $this->headersData = $data;
    }

    public function add(array $parameters = []): void
    {
        foreach ($parameters as $headerKey => $headerValue) {
            $this->setHeader($headerKey, $headerValue);
        }
    }

    public function all(string ...$keys): array
    {
        if (empty($keys)) {
            return $this->headersData;
        }

        $resultSet = [];
        foreach ($keys as $headerKey) {
            $resultSet[$headerKey] = $this->fetch($headerKey);
        }
        return $resultSet;
    }

    public function fetch(string $headerKey, $defaultValue = null): ?string
    {
        $possibleKeys = [
            $headerKey,
            'HTTP_' . strtoupper(str_replace('-', '_', $headerKey)),
            strtoupper(str_replace('-', '_', $headerKey)),
        ];
        
        foreach ($possibleKeys as $possibleKey) {
            if (array_key_exists($possibleKey, $this->headersData)) {
                return $this->headersData[$possibleKey];
            }
        }

        return $defaultValue;
    }

    public function get(string $headerKey, $defaultValue = null): ?string
    {
        return $this->fetch($headerKey, $defaultValue);
    }

    public function getArray(string $headerKey, bool $allowNull = true): ?array
    {
        $headerValue = null;
        $possibleKeys = [
            $headerKey,
            'HTTP_' . strtoupper(str_replace('-', '_', $headerKey)),
            strtoupper(str_replace('-', '_', $headerKey)),
        ];
        
        foreach ($possibleKeys as $possibleKey) {
            if (array_key_exists($possibleKey, $this->headersData)) {
                $headerValue = $this->headersData[$possibleKey];
                break;
            }
        }
        
        if ($allowNull && empty($headerValue)) {
            return null;
        }

        if (is_array($headerValue)) {
            return $headerValue;
        }

        return (array)$headerValue;
    }

    public function getAlnum(string $headerKey): string
    {
        return preg_replace('/[^[:alnum:]]/', '', $this->fetch($headerKey) ?? '');
    }

    public function getBool(string $headerKey, bool $allowNull = true): ?bool
    {
        $headerValue = $this->fetch($headerKey);
        if ($allowNull && is_null($headerValue)) {
            return null;
        }

        return (bool)$headerValue;
    }

    public function getDate(string $headerKey, bool $allowNull = true): ?string
    {
        $headerValue = $this->fetch($headerKey);
        if (Validator::isValidDate($headerValue ?? '')) {
            return Tools::formatDate($headerValue);
        }

        return $allowNull ? null : '';
    }

    public function getDateTime(string $headerKey, bool $allowNull = true): ?string
    {
        $headerValue = $this->fetch($headerKey);
        if (Validator::isValidDateTime($headerValue ?? '') || Validator::isValidDate($headerValue ?? '')) {
            return Tools::formatDateTime($headerValue);
        }

        return $allowNull ? null : '';
    }

    public function getEmail(string $headerKey, bool $allowNull = true): ?string
    {
        $headerValue = $this->fetch($headerKey);
        if (Validator::isValidEmail($headerValue ?? '')) {
            return $headerValue;
        }

        return $allowNull ? null : '';
    }

    public function getFloat(string $headerKey, bool $allowNull = true): ?float
    {
        $headerValue = $this->fetch($headerKey);
        if ($allowNull && is_null($headerValue)) {
            return null;
        }

        return (float)str_replace(',', '.', $headerValue);
    }

    public function getHour(string $headerKey, bool $allowNull = true): ?string
    {
        $headerValue = $this->fetch($headerKey);
        if (Validator::isValidHour($headerValue ?? '')) {
            return Tools::formatHour($headerValue);
        }

        return $allowNull ? null : '';
    }

    public function getInt(string $headerKey, bool $allowNull = true): ?int
    {
        $headerValue = $this->fetch($headerKey);
        if ($allowNull && is_null($headerValue)) {
            return null;
        }

        return (int)$headerValue;
    }

    public function getOnly(string $headerKey, array $allowedValues): ?string
    {
        $headerValue = $this->fetch($headerKey);
        if (in_array($headerValue, $allowedValues)) {
            return $headerValue;
        }

        return null;
    }

    public function getString(string $headerKey, bool $allowNull = true): ?string
    {
        $headerValue = $this->fetch($headerKey);
        if ($allowNull && is_null($headerValue)) {
            return null;
        }

        return (string)$headerValue;
    }

    public function getUrl(string $headerKey, bool $allowNull = true): ?string
    {
        $headerValue = $this->fetch($headerKey);
        if (Validator::isValidUrl($headerValue ?? '')) {
            return $headerValue;
        }

        return $allowNull ? null : '';
    }

    public function has(string ...$keys): bool
    {
        foreach ($keys as $headerKey) {
            $found = false;
            $possibleKeys = [
                $headerKey,
                'HTTP_' . strtoupper(str_replace('-', '_', $headerKey)),
                strtoupper(str_replace('-', '_', $headerKey)),
            ];
            
            foreach ($possibleKeys as $possibleKey) {
                if (array_key_exists($possibleKey, $this->headersData)) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                return false;
            }
        }

        return true;
    }

    public function isMissing(string ...$keys): bool
    {
        foreach ($keys as $headerKey) {
            if (!$this->has($headerKey)) {
                return false;
            }
        }

        return true;
    }

    public function remove(string $headerKey): void
    {
        $possibleKeys = [
            $headerKey,
            'HTTP_' . strtoupper(str_replace('-', '_', $headerKey)),
            strtoupper(str_replace('-', '_', $headerKey)),
        ];
        
        foreach ($possibleKeys as $possibleKey) {
            if (array_key_exists($possibleKey, $this->headersData)) {
                unset($this->headersData[$possibleKey]);
            }
        }
    }

    public function setHeader(string $headerKey, $headerValue): void
    {
        $this->headersData[$headerKey] = $headerValue;
    }

    public function set(string $headerKey, $headerValue): void
    {
        $this->setHeader($headerKey, $headerValue);
    }
}
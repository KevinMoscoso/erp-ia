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

final class SubRequest
{
    /** @var array */
    private $requestData;

    public function __construct(array $inputData)
    {
        $this->requestData = $inputData;
    }

    public function add(array $parameters = []): void
    {
        foreach ($parameters as $key => $value) {
            $this->assign($key, $value);
        }
    }

    public function all(string ...$keys): array
    {
        if (empty($keys)) {
            return $this->requestData;
        }

        $resultSet = [];
        foreach ($keys as $key) {
            $resultSet[$key] = $this->fetch($key);
        }
        return $resultSet;
    }

    public function fetch(string $key, $default = null): ?string
    {
        $value = $this->requestData[$key] ?? $default;

        if (is_array($value)) {
            return serialize($value);
        }

        return $value;
    }

    public function get(string $key, $default = null): ?string
    {
        return $this->fetch($key, $default);
    }

    public function getArray(string $key): array
    {
        $value = $this->requestData[$key] ?? [];

        if (is_array($value)) {
            return $value;
        }

        return [];
    }

    public function getAlnum(string $key): string
    {
        return preg_replace('/[^[:alnum:]]/', '', $this->fetch($key) ?? '');
    }

    public function getBool(string $key, ?bool $default = null): ?bool
    {
        $value = $this->fetch($key);
        if (is_null($value)) {
            return $default;
        }

        return (bool)$value;
    }

    public function getDate(string $key, ?string $default = null): ?string
    {
        $value = $this->fetch($key);
        if (Validator::isValidDate($value ?? '')) {
            return Tools::formatDate($value);
        }

        return $default;
    }

    public function getDateTime(string $key, ?string $default = null): ?string
    {
        $value = $this->fetch($key);
        if (Validator::isValidDateTime($value ?? '') || Validator::isValidDate($value ?? '')) {
            return Tools::formatDateTime($value);
        }

        return $default;
    }

    public function getEmail(string $key, ?string $default = null): ?string
    {
        $value = $this->fetch($key);
        if (Validator::isValidEmail($value ?? '')) {
            return strtolower($value);
        }

        return $default;
    }

    public function getFloat(string $key, ?float $default = null): ?float
    {
        $value = $this->fetch($key);
        if (is_null($value)) {
            return $default;
        }

        return (float)str_replace(',', '.', $value);
    }

    public function getHour(string $key, ?string $default = null): ?string
    {
        $value = $this->fetch($key);
        if (Validator::isValidHour($value ?? '')) {
            return Tools::formatHour($value);
        }

        return $default;
    }

    public function getInt(string $key, ?int $default = null): ?int
    {
        $value = $this->fetch($key);
        if (is_null($value)) {
            return $default;
        }

        return (int)$value;
    }

    public function getOnly(string $key, array $allowedValues): ?string
    {
        $value = $this->fetch($key);
        if (in_array($value, $allowedValues)) {
            return $value;
        }

        return null;
    }

    public function getString(string $key, ?string $default = null): ?string
    {
        $value = $this->fetch($key);
        if (is_null($value)) {
            return $default;
        }

        return (string)$value;
    }

    public function getUrl(string $key, ?string $default = null): ?string
    {
        $value = $this->fetch($key);
        if (Validator::isValidUrl($value ?? '')) {
            return $value;
        }

        return $default;
    }

    public function has(string ...$keys): bool
    {
        foreach ($keys as $key) {
            if (!isset($this->requestData[$key])) {
                return false;
            }
        }
        return true;
    }

    public function isMissing(string ...$keys): bool
    {
        foreach ($keys as $key) {
            if (isset($this->requestData[$key])) {
                return false;
            }
        }
        return true;
    }

    public function remove(string $key): void
    {
        unset($this->requestData[$key]);
    }

    public function assign(string $key, $value): void
    {
        $this->requestData[$key] = $value;
    }

    public function set(string $key, $value): void
    {
        $this->assign($key, $value);
    }
}
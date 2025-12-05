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

final class ResponseHeaders
{
    /** @var array */
    private $headers = [];

    public function __construct()
    {
        $this->headers = [
            'Content-Type' => 'text/html; charset=utf-8',
            'Strict-Transport-Security' => 'max-age=63072000; includeSubDomains',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
        ];
    }

    public function all(): array
    {
        return $this->headers;
    }

    public function get(string $name): string
    {
        return $this->headers[$name] ?? '';
    }

    public function remove(string $name): self
    {
        unset($this->headers[$name]);

        return $this;
    }

    public function set(string $name, string $value): self
    {
        $this->headers[$name] = $value;

        return $this;
    }
}
<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2013-2025 ERPIA Contributors
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
 * Utils provides basic and common utility methods
 *
 * @author ERPIA Contributors
 */
class Utils
{
    /**
     * Convert binary data to base64 encoded string
     */
    public static function binaryToString($binaryData): string
    {
        return $binaryData === null ? 'NULL' : "'" . base64_encode($binaryData) . "'";
    }

    /**
     * Convert boolean to string representation
     */
    public static function boolToString(bool $value): string
    {
        if ($value === true) {
            return 't';
        }
        
        return 'f';
    }

    /**
     * Generate date range between two dates
     */
    public static function dateRange(string $startDate, string $endDate, string $interval = '+1 day', string $format = 'd-m-Y'): array
    {
        $dates = [];
        $current = strtotime($startDate);
        $last = strtotime($endDate);

        while ($current <= $last) {
            $dates[] = date($format, $current);
            $current = strtotime($interval, $current);
        }

        return $dates;
    }

    /**
     * Fix HTML encoded characters
     */
    public static function fixHtml(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $replacements = [
            '&lt;' => '<',
            '&gt;' => '>', 
            '&quot;' => '"',
            '&#39;' => "'"
        ];

        return trim(str_replace(array_keys($replacements), array_values($replacements), $text));
    }

    /**
     * Compare two floating point numbers with specified precision
     */
    public static function floatCmp(float $num1, float $num2, int $precision = 10, bool $useRounding = false): bool
    {
        if ($useRounding || !function_exists('bccomp')) {
            return abs($num1 - $num2) < 6 / pow(10, $precision + 1);
        }

        return bccomp((string)$num1, (string)$num2, $precision) === 0;
    }

    /**
     * Safe integer conversion with null handling
     */
    public static function intVal(?string $value): ?int
    {
        return $value === null ? null : (int)$value;
    }

    /**
     * Validate and fix URL format
     */
    public static function isValidUrl(string $url): bool
    {
        if (empty($url) || stripos($url, 'javascript:') === 0) {
            return false;
        }

        if (stripos($url, 'www.') === 0) {
            $url = 'https://' . $url;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Escape HTML special characters
     */
    public static function escapeHtml(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $escapeMap = [
            '<' => '&lt;',
            '>' => '&gt;',
            '"' => '&quot;',
            "'" => '&#39;'
        ];

        return trim(str_replace(array_keys($escapeMap), array_values($escapeMap), $text));
    }

    /**
     * Normalize string by removing diacritics
     */
    public static function normalize(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $diacriticMap = [
            'Š' => 'S', 'š' => 's', 'Đ' => 'Dj', 'đ' => 'dj', 'Ž' => 'Z', 'ž' => 'z', 'Č' => 'C', 'č' => 'c',
            'Ć' => 'C', 'ć' => 'c', 'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A',
            'Æ' => 'A', 'Ç' => 'C', 'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I',
            'Î' => 'I', 'Ï' => 'I', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O',
            'Ø' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ý' => 'Y', 'Þ' => 'B', 'ß' => 'Ss',
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'a', 'ç' => 'c',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ð' => 'o', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ý' => 'y', 'þ' => 'b', 'ÿ' => 'y', 'Ŕ' => 'R', 'ŕ' => 'r'
        ];

        return strtr($text, $diacriticMap);
    }

    /**
     * Generate random alphanumeric string
     */
    public static function randomString(int $length = 10): string
    {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $shuffled = str_shuffle($characters);
        return mb_substr($shuffled, 0, $length);
    }

    /**
     * Convert base64 string to binary data
     */
    public static function stringToBinary(?string $base64String)
    {
        return $base64String === null ? null : base64_decode($base64String);
    }

    /**
     * Convert string to boolean
     */
    public static function stringToBool(string $value): bool
    {
        $lowerValue = strtolower($value);
        return in_array($lowerValue, ['true', 't', '1'], true);
    }

    /**
     * Break text without cutting words
     */
    public static function textBreak(?string $text, int $maxWidth = 500): ?string
    {
        if ($text === null) {
            return null;
        }

        // Remove extra whitespace
        $cleaned = trim(preg_replace('/\s+/', ' ', $text));
        
        if (mb_strlen($cleaned) <= $maxWidth) {
            return $cleaned;
        }

        $result = '';
        $words = explode(' ', $cleaned);
        
        foreach ($words as $word) {
            if (mb_strlen($result . ' ' . $word) >= $maxWidth - 3) {
                break;
            }
            
            if ($result === '') {
                $result = $word;
            } else {
                $result .= ' ' . $word;
            }
        }

        return $result . '...';
    }
}
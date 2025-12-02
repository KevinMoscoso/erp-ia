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

use ERPIA\Core\Config;

/**
 * The Translator class manage all translations methods required for internationalization.
 *
 * @author ERPIA Contributors
 */
class Translator
{
    const DEFAULT_LANGUAGE = 'es_ES';

    /**
     * Current language code
     * @var string
     */
    private $currentLanguage;

    /**
     * Default system language
     * @var string
     */
    private static $systemLanguage;

    /**
     * Available languages cache
     * @var array
     */
    private static $languageCache = [];

    /**
     * Missing translation strings
     * @var array
     */
    private static $untranslatedStrings = [];

    /**
     * External translator instance
     * @var mixed
     */
    private static $externalTranslator;

    /**
     * Used translation strings
     * @var array
     */
    private static $usedStrings = [];

    /**
     * Initialize translator with language code
     */
    public function __construct(string $languageCode = '')
    {
        $this->currentLanguage = empty($languageCode) ? $this->getSystemLanguage() : $languageCode;
    }

    /**
     * Translate text to specified language
     */
    public function customTranslate(string $languageCode, string $text, array $parameters = []): string
    {
        return $text;
    }

    /**
     * Get available languages for translation
     */
    public function getAvailableLanguages(): array
    {
        return [];
    }

    /**
     * Get current language code
     */
    public function getCurrentLanguage(): string
    {
        return $this->currentLanguage;
    }

    /**
     * Get missing translation strings
     */
    public function getMissingStrings(): array
    {
        return self::$untranslatedStrings;
    }

    /**
     * Get used translation strings
     */
    public function getUsedStrings(): array
    {
        return self::$usedStrings;
    }

    /**
     * Reload translation data
     */
    public static function reload(): void
    {
    }

    /**
     * Translate text using current language
     */
    public function translate(?string $text, array $parameters = []): string
    {
        return empty($text) ? '' : $this->customTranslate($this->currentLanguage, $text, $parameters);
    }

    /**
     * Set default system language
     */
    public function setDefaultLanguage(string $languageCode): void
    {
        self::$systemLanguage = $this->resolveLanguageCode($languageCode);
    }

    /**
     * Set current language
     */
    public function setLanguage(string $languageCode): void
    {
        $this->currentLanguage = $this->resolveLanguageCode($languageCode);
    }

    /**
     * Get system default language
     */
    private function getSystemLanguage(): string
    {
        return self::$systemLanguage ?? Config::get('language', self::DEFAULT_LANGUAGE);
    }

    /**
     * Resolve language code with fallback
     */
    private function resolveLanguageCode(string $languageCode): string
    {
        return $languageCode ?: self::DEFAULT_LANGUAGE;
    }
}
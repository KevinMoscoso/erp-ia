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

use ERPIA\Core\Base\Contract\LogStorageInterface;

/**
 * Manages application logging with multiple severity levels
 *
 * @author ERPIA Contributors
 */
final class MiniLog
{
    const DEFAULT_CHANNEL = 'system';
    const MESSAGE_LIMIT = 5000;

    /** @var string */
    private $channel;

    /** @var array */
    private static $globalContext = [];

    /** @var array */
    private static $logEntries = [];

    /** @var bool */
    private static $loggingDisabled = false;

    /** @var LogStorageInterface */
    private static $storageEngine;

    /** @var Translator|null */
    private $translator;

    public function __construct(string $channel = '', $translator = null)
    {
        $this->channel = empty($channel) ? self::DEFAULT_CHANNEL : $channel;
        $this->translator = $translator;
    }

    /**
     * Clear log entries for specific channel or all channels
     */
    public static function clear(string $channel = ''): void
    {
        if (empty($channel)) {
            self::$logEntries = [];
            return;
        }

        foreach (self::$logEntries as $index => $entry) {
            if ($entry['channel'] === $channel) {
                unset(self::$logEntries[$index]);
            }
        }
    }

    /**
     * Log critical system conditions
     */
    public function critical(string $message, array $context = []): void
    {
        $this->addLogEntry('critical', $message, $context);
    }

    /**
     * Log detailed debug information
     */
    public function debug(string $message, array $context = []): void
    {
        if (ERPIA_DEBUG) {
            $this->addLogEntry('debug', $message, $context);
        }
    }

    /**
     * Enable or disable logging
     */
    public static function setEnabled(bool $enabled = true): void
    {
        self::$loggingDisabled = !$enabled;
    }

    /**
     * Log runtime errors
     */
    public function error(string $message, array $context = []): void
    {
        $this->addLogEntry('error', $message, $context);
    }

    /**
     * Get context value by key
     */
    public static function getContext(string $key): string
    {
        return self::$globalContext[$key] ?? '';
    }

    /**
     * Log informational messages
     */
    public function info(string $message, array $context = []): void
    {
        $this->addLogEntry('info', $message, $context);
    }

    /**
     * Log significant normal events
     */
    public function notice(string $message, array $context = []): void
    {
        $this->addLogEntry('notice', $message, $context);
    }

    /**
     * Read log entries for channel and levels
     */
    public static function read(string $channel = '', array $levels = []): array
    {
        $filteredEntries = [];
        foreach (self::$logEntries as $entry) {
            if ($channel && $entry['channel'] != $channel) {
                continue;
            }

            if ($levels && !in_array($entry['level'], $levels)) {
                continue;
            }

            $filteredEntries[] = $entry;
        }

        return $filteredEntries;
    }

    /**
     * Save log entries to storage
     */
    public static function save(string $channel = ''): bool
    {
        if (!isset(self::$storageEngine)) {
            self::$storageEngine = new LogStorage();
        }

        $entriesToSave = empty($channel) ? self::$logEntries : self::read($channel);
        self::clear($channel);

        self::setEnabled(false);
        $result = self::$storageEngine->persist($entriesToSave);
        self::setEnabled(true);

        return $result;
    }

    /**
     * Set context value for key
     */
    public static function setContext(string $key, string $value): void
    {
        self::$globalContext[$key] = $value;
    }

    /**
     * Set custom storage engine
     */
    public static function setStorage(LogStorageInterface $storage): void
    {
        self::$storageEngine = $storage;
    }

    /**
     * Log warning conditions
     */
    public function warning(string $message, array $context = []): void
    {
        $this->addLogEntry('warning', $message, $context);
    }

    /**
     * Internal log entry creation
     */
    private function addLogEntry(string $level, string $message, array $context = []): void
    {
        if (empty($message) || self::$loggingDisabled) {
            return;
        }

        $fullContext = array_merge($context, self::$globalContext);
        $translatedMessage = $this->translator ? 
            $this->translator->translate($message, $context) : $message;

        // Check for duplicate entries to increment count
        foreach (self::$logEntries as $index => $entry) {
            if ($entry['channel'] === $this->channel && 
                $entry['level'] === $level &&
                $entry['message'] === $translatedMessage && 
                $entry['context'] === $fullContext) {
                self::$logEntries[$index]['count']++;
                return;
            }
        }

        // Add new log entry
        self::$logEntries[] = [
            'channel' => $this->channel,
            'context' => $fullContext,
            'count' => 1,
            'level' => $level,
            'message' => $translatedMessage,
            'original' => $message,
            'timestamp' => $context['time'] ?? microtime(true),
        ];

        $this->checkLimit();
    }

    /**
     * Save and clear if limit exceeded
     */
    private function checkLimit(): void
    {
        if (count(self::$logEntries) > self::MESSAGE_LIMIT) {
            self::save($this->channel);
        }
    }
}
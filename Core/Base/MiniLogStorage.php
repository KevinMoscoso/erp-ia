<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2021-2025 ERPIA Contributors
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
use ERPIA\Core\Session;
use ERPIA\Dinamic\Model\LogMessage;

/**
 * Log storage implementation for database persistence
 *
 * @author ERPIA Contributors
 */
final class MiniLogStorage implements LogStorageInterface
{
    /**
     * Persist log entries to database
     */
    public function persist(array $logEntries): bool
    {
        $success = true;
        foreach ($logEntries as $entry) {
            // Skip debug level entries
            if ($entry['level'] === 'debug') {
                continue;
            }

            // For system channel, only store critical and error levels
            if ($entry['channel'] === MiniLog::DEFAULT_CHANNEL && 
                !in_array($entry['level'], ['critical', 'error'])) {
                continue;
            }

            // Create and save log record
            $logRecord = new LogMessage();
            $logRecord->channel = $entry['channel'];
            $logRecord->context = json_encode($entry['context']);
            $logRecord->contact_id = $entry['context']['contact_id'] ?? null;
            $logRecord->client_ip = Session::getClientIP();
            $logRecord->level = $entry['level'];
            $logRecord->message = $entry['message'];
            $logRecord->model = $entry['context']['model-class'] ?? null;
            $logRecord->model_id = $entry['context']['model-id'] ?? null;
            $logRecord->user_id = $entry['context']['user_id'] ?? Session::user()->username;
            $logRecord->timestamp = date('Y-m-d H:i:s', (int)$entry['timestamp']);
            $logRecord->uri = $entry['context']['uri'] ?? Session::get('uri');
            
            if (!$logRecord->save()) {
                $success = false;
            }
        }

        return $success;
    }
}
<?php
/**
 * This file is part of ERPIA
 * Copyright (C) 2015-2025 ERPIA Contributors
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

use ERPIA\Core\Base\DataBase\DataBaseEngine;
use ERPIA\Core\Base\DataBase\MysqlEngine;
use ERPIA\Core\Base\DataBase\PostgresqlEngine;
use ERPIA\Core\AppException;
use ERPIA\Core\Config;
use ERPIA\Core\Logger;

/**
 * Generic database access class for MySQL and PostgreSQL
 *
 * @author ERPIA Contributors
 */
final class DataBase
{
    const LOG_CHANNEL = 'database';
    
    /**
     * Database engine instance
     * @var DataBaseEngine
     */
    private static $dbEngine;
    
    /**
     * Database connection resource
     * @var resource
     */
    private static $connection;
    
    /**
     * Logger instance
     * @var Logger
     */
    private static $logger;
    
    /**
     * Cached table list
     * @var array
     */
    private static $tableCache = [];
    
    /**
     * Database type
     * @var string
     */
    private static $dbType;

    /**
     * Initialize database connection
     */
    public function __construct()
    {
        if (Config::get('db_name') && self::$connection === null) {
            self::$logger = new Logger(self::LOG_CHANNEL);
            self::$dbType = strtolower(Config::get('db_type'));
            
            switch (self::$dbType) {
                case 'postgresql':
                    self::$dbEngine = new PostgresqlEngine();
                    break;
                default:
                    self::$dbEngine = new MysqlEngine();
                    break;
            }
        }
    }

    /**
     * Start database transaction
     */
    public function beginTransaction(): bool
    {
        if ($this->inTransaction()) {
            return true;
        }
        
        self::$logger->debug('Starting transaction');
        return self::$dbEngine->beginTransaction(self::$connection);
    }

    /**
     * Cast column to integer
     */
    public function castInteger(string $column): string
    {
        return self::$dbEngine->castInteger(self::$connection, $column);
    }

    /**
     * Close database connection
     */
    public function close(): bool
    {
        if (!$this->isConnected()) {
            return true;
        }
        
        if (self::$dbEngine->inTransaction(self::$connection) && !$this->rollback()) {
            return false;
        }
        
        if (self::$dbEngine->close(self::$connection)) {
            self::$connection = null;
        }

        return !$this->isConnected();
    }

    /**
     * Commit transaction
     */
    public function commit(): bool
    {
        $success = self::$dbEngine->commit(self::$connection);
        if ($success) {
            self::$logger->debug('Transaction committed');
        }
        return $success;
    }

    /**
     * Connect to database
     */
    public function connect(): bool
    {
        if ($this->isConnected()) {
            return true;
        }
        
        $error = '';
        self::$connection = self::$dbEngine->connect($error);
        
        if (!empty($error)) {
            self::$logger->critical($error);
        }
        
        return $this->isConnected();
    }

    /**
     * Check connection status
     */
    public function isConnected(): bool
    {
        return (bool) self::$connection;
    }

    /**
     * Escape column name
     */
    public function escapeColumn(string $name): string
    {
        return self::$dbEngine->escapeColumn(self::$connection, $name);
    }

    /**
     * Escape string value
     */
    public function escapeString($value): string
    {
        return self::$dbEngine->escapeString(self::$connection, $value);
    }

    /**
     * Execute SQL statement (INSERT, UPDATE, DELETE)
     */
    public function execute(string $sql): bool
    {
        if (!$this->isConnected()) {
            return false;
        }

        // Clear table cache due to potential changes
        self::$tableCache = [];
        
        $inTransaction = $this->inTransaction();
        $this->beginTransaction();
        
        $startTime = microtime(true);
        $result = self::$dbEngine->exec(self::$connection, $sql);
        $endTime = microtime(true);
        
        self::$logger->debug($sql, ['duration' => $endTime - $startTime]);
        
        if (!$result || self::$dbEngine->hasError()) {
            self::$logger->error(self::$dbEngine->errorMessage(self::$connection), ['sql' => $sql]);
            self::$dbEngine->clearError();
        }
        
        if ($inTransaction) {
            return $result;
        }
        
        if ($result) {
            return $this->commit();
        }
        
        $this->rollback();
        return false;
    }

    /**
     * Get table columns
     */
    public function getColumns(string $tableName): array
    {
        $columns = [];
        $data = $this->select(self::$dbEngine->getSQL()->sqlColumns($tableName));
        
        foreach ($data as $row) {
            $column = self::$dbEngine->columnFromData($row);
            $columns[$column['name']] = $column;
        }
        
        return $columns;
    }

    /**
     * Get table constraints
     */
    public function getConstraints(string $tableName, bool $extended = false): array
    {
        $sql = $extended ?
            self::$dbEngine->getSQL()->sqlConstraintsExtended($tableName) :
            self::$dbEngine->getSQL()->sqlConstraints($tableName);
            
        $data = $this->select($sql);
        return $data ? array_values($data) : [];
    }

    /**
     * Get database engine
     */
    public function getEngine(): DataBaseEngine
    {
        return self::$dbEngine;
    }

    /**
     * Get all table indexes
     */
    public function getAllIndexes(string $tableName): array
    {
        $indexes = [];
        $data = $this->select(self::$dbEngine->getSQL()->sqlIndexes($tableName));
        
        foreach ($data as $row) {
            $indexes[] = [
                'name' => $row['Key_name'] ?? $row['key_name'] ?? '',
                'column' => $row['Column_name'] ?? $row['column_name'] ?? '',
            ];
        }
        
        return $indexes;
    }

    /**
     * Get ERPIA-specific indexes
     */
    public function getIndexes(string $tableName): array
    {
        $allIndexes = $this->getAllIndexes($tableName);
        $erpiaIndexes = array_filter($allIndexes, function ($index) {
            return strpos($index['name'], 'erpia_') !== false;
        });
        
        return array_values($erpiaIndexes);
    }

    /**
     * Get database operator
     */
    public function getOperator(string $operator): string
    {
        return self::$dbEngine->getOperator($operator);
    }

    /**
     * Get database tables
     */
    public function getTables(): array
    {
        if (!$this->isConnected()) {
            return [];
        }
        
        if (empty(self::$tableCache)) {
            self::$tableCache = self::$dbEngine->listTables(self::$connection);
        }
        
        return self::$tableCache;
    }

    /**
     * Check if in transaction
     */
    public function inTransaction(): bool
    {
        return self::$dbEngine->inTransaction(self::$connection);
    }

    /**
     * Get last insert ID
     */
    public function lastValue()
    {
        $result = $this->select(self::$dbEngine->getSQL()->sqlLastValue());
        return empty($result) ? false : $result[0]['num'];
    }

    /**
     * Rollback transaction
     */
    public function rollback(): bool
    {
        self::$logger->debug('Rolling back transaction');
        return self::$dbEngine->rollback(self::$connection);
    }

    /**
     * Execute SELECT query
     */
    public function select(string $sql): array
    {
        return $this->selectWithLimit($sql, 0);
    }

    /**
     * Execute SELECT query with limit and offset
     */
    public function selectWithLimit(string $sql, int $limit = ERPIA_ITEM_LIMIT, int $offset = 0): array
    {
        if (!$this->isConnected()) {
            return [];
        }
        
        if ($limit > 0) {
            $sql .= ' LIMIT ' . $limit . ' OFFSET ' . $offset . ';';
        }
        
        $startTime = microtime(true);
        $result = self::$dbEngine->select(self::$connection, $sql);
        $endTime = microtime(true);
        
        self::$logger->debug($sql, ['duration' => $endTime - $startTime]);
        
        if (!empty($result)) {
            return $result;
        }
        
        $error = self::$dbEngine->errorMessage(self::$connection);
        if (!empty($error)) {
            self::$logger->error($error, ['sql' => $sql]);
        }
        
        return [];
    }

    /**
     * Check if table exists
     */
    public function tableExists(string $tableName, array $tableList = []): bool
    {
        if (empty($tableList)) {
            $tableList = $this->getTables();
        }
        
        return in_array($tableName, $tableList, true);
    }

    /**
     * Get database type
     */
    public function getType(): string
    {
        return self::$dbType ?? '';
    }

    /**
     * Update sequences (PostgreSQL)
     */
    public function updateSequence(string $tableName, array $fields): void
    {
        self::$dbEngine->updateSequence(self::$connection, $tableName, $fields);
    }

    /**
     * Convert PHP value to SQL string
     */
    public function valueToSql($value): string
    {
        if ($value === null) {
            return 'NULL';
        }
        
        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }
        
        if (is_array($value)) {
            throw new AppException('DatabaseError', 'Array conversion not supported');
        }
        
        if (is_object($value)) {
            throw new AppException('DatabaseError', 'Object conversion not supported');
        }
        
        // Date handling
        if (preg_match("/^([\d]{1,2})-([\d]{1,2})-([\d]{4})$/i", $value)) {
            $date = date(self::$dbEngine->dateFormat(), strtotime($value));
            return $date === '1970-01-01' ?
                "'" . $this->escapeString($value) . "'" :
                "'" . $date . "'";
        }
        
        // DateTime handling
        if (preg_match("/^([\d]{1,2})-([\d]{1,2})-([\d]{4}) ([\d]{1,2}):([\d]{1,2}):([\d]{1,2})$/i", $value)) {
            $datetime = date(self::$dbEngine->dateFormat() . ' H:i:s', strtotime($value));
            return $datetime === '1970-01-01 00:00:00' ?
                "'" . $this->escapeString($value) . "'" :
                "'" . $datetime . "'";
        }
        
        return "'" . $this->escapeString($value) . "'";
    }

    /**
     * Get database version
     */
    public function version(): string
    {
        return $this->isConnected() ? self::$dbEngine->version(self::$connection) : '';
    }
}
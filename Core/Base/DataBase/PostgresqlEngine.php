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

namespace ERPIA\Core\Base\DataBase;

use Exception;
use ERPIA\Core\AppException;
use ERPIA\Core\Config;

/**
 * Class to connect with PostgreSQL database engine
 *
 * @author ERPIA Contributors
 */
class PostgresqlEngine extends DataBaseEngine
{
    /**
     * Link to the SQL statements generator for PostgreSQL
     *
     * @var DataBaseQueries
     */
    private $sqlGenerator;

    /**
     * Constructor and initialization
     */
    public function __construct()
    {
        parent::__construct();
        $this->sqlGenerator = new PostgresqlQueries();
    }

    /**
     * Starts a SQL transaction
     */
    public function beginTransaction($link): bool
    {
        return $this->executeStatement($link, 'START TRANSACTION;');
    }

    /**
     * Casts column to integer for PostgreSQL
     */
    public function castInteger($link, $column): string
    {
        return 'CAST(' . $this->escapeColumn($link, $column) . ' AS INTEGER)';
    }

    /**
     * Disconnects from the database
     */
    public function close($link): bool
    {
        return pg_close($link);
    }

    /**
     * Converts column data to standardized structure
     */
    public function columnFromData($colData): array
    {
        $colData['extra'] = null;

        if ($colData['character_maximum_length'] !== null) {
            $colData['type'] .= '(' . $colData['character_maximum_length'] . ')';
        }

        return $colData;
    }

    /**
     * Commits transaction changes
     */
    public function commit($link): bool
    {
        return $this->executeStatement($link, 'COMMIT;');
    }

    /**
     * Establishes connection to PostgreSQL database
     */
    public function connect(&$error)
    {
        if (!function_exists('pg_connect')) {
            $error = $this->translator->trans('postgresql-extension-not-available');
            throw new AppException('DatabaseConnectionError', $error);
        }

        $connectionParams = [
            'host' => Config::get('db_host'),
            'dbname' => Config::get('db_name'),
            'port' => Config::get('db_port'),
            'user' => Config::get('db_user'),
            'password' => Config::get('db_pass')
        ];

        $connectionString = $this->buildConnectionString($connectionParams);
        
        $connection = pg_connect($connectionString);
        if (!$connection) {
            $error = pg_last_error();
            throw new AppException('DatabaseConnectionError', $error);
        }

        // Configure date format
        $this->executeStatement($connection, 'SET DATESTYLE TO ISO, YMD;');
        return $connection;
    }

    /**
     * Returns last error message
     */
    public function errorMessage($link): string
    {
        $pgError = pg_last_error($link);
        return empty($pgError) ? $this->lastErrorMessage : $pgError;
    }

    /**
     * Escapes column names for PostgreSQL
     */
    public function escapeColumn($link, $name): string
    {
        return '"' . $name . '"';
    }

    /**
     * Escapes string values for safe database insertion
     */
    public function escapeString($link, $str): string
    {
        return pg_escape_string($link, $str);
    }

    /**
     * Executes SQL statements (INSERT, UPDATE, DELETE)
     */
    public function exec($link, $sql): bool
    {
        return $this->runQuery($link, $sql, false) === true;
    }

    /**
     * Returns database-specific operator
     */
    public function getOperator($operator): string
    {
        switch ($operator) {
            case 'REGEXP':
                return '~';
            default:
                return $operator;
        }
    }

    /**
     * Returns SQL generator instance
     */
    public function getSQL()
    {
        return $this->sqlGenerator;
    }

    /**
     * Checks if connection is in transaction state
     */
    public function inTransaction($link): bool
    {
        $transactionStatus = pg_transaction_status($link);
        return in_array($transactionStatus, [
            PGSQL_TRANSACTION_ACTIVE,
            PGSQL_TRANSACTION_INTRANS,
            PGSQL_TRANSACTION_INERROR
        ], true);
    }

    /**
     * Lists all tables in the database
     */
    public function listTables($link): array
    {
        $tableList = [];
        $query = "SELECT table_name FROM information_schema.tables 
                 WHERE table_schema NOT IN ('pg_catalog', 'information_schema') 
                 AND table_type = 'BASE TABLE'
                 ORDER BY table_name;";

        foreach ($this->select($link, $query) as $tableRow) {
            $tableList[] = $tableRow['table_name'];
        }

        return $tableList;
    }

    /**
     * Rolls back current transaction
     */
    public function rollback($link): bool
    {
        return $this->executeStatement($link, 'ROLLBACK;');
    }

    /**
     * Executes SELECT query and returns results
     */
    public function select($link, $sql): array
    {
        $queryResult = $this->runQuery($link, $sql);
        return is_array($queryResult) ? $queryResult : [];
    }

    /**
     * Updates sequence values for serial columns
     */
    public function updateSequence($link, $tableName, $fields): void
    {
        foreach ($fields as $columnName => $fieldInfo) {
            if (!empty($fieldInfo['default']) && stripos($fieldInfo['default'], 'nextval(') !== false) {
                $sequenceUpdate = "SELECT setval('{$tableName}_{$columnName}_seq', 
                                  (SELECT MAX({$columnName}) FROM {$tableName}));";
                $this->executeStatement($link, $sequenceUpdate);
            }
        }
    }

    /**
     * Returns database version information
     */
    public function version($link): string
    {
        $versionData = pg_version($link);
        return 'POSTGRESQL ' . ($versionData['server'] ?? 'Unknown');
    }

    /**
     * Builds PostgreSQL connection string
     */
    private function buildConnectionString(array $params): string
    {
        $connectionParts = [];
        foreach ($params as $key => $value) {
            if (!empty($value)) {
                $connectionParts[] = $key . '=' . $value;
            }
        }

        // Add SSL configuration if enabled
        if (Config::get('pgsql_ssl_enabled', false)) {
            $connectionParts[] = 'sslmode=' . Config::get('pgsql_ssl_mode', 'prefer');
        }

        // Add endpoint configuration if specified
        if (Config::get('pgsql_endpoint')) {
            $connectionParts[] = "options='--endpoint=" . Config::get('pgsql_endpoint') . "'";
        }

        return implode(' ', $connectionParts);
    }

    /**
     * Executes a simple SQL statement
     */
    private function executeStatement($link, $sql): bool
    {
        return $this->runQuery($link, $sql, false) === true;
    }

    /**
     * Executes SQL query and handles results
     */
    private function runQuery($link, $sql, $returnResults = true)
    {
        $result = $returnResults ? [] : false;

        try {
            $queryResult = @pg_query($link, $sql);
            if ($queryResult) {
                if ($returnResults) {
                    $result = pg_fetch_all($queryResult) ?: [];
                } else {
                    $result = true;
                }
                pg_free_result($queryResult);
            }
        } catch (Exception $exception) {
            $this->lastErrorMessage = $exception->getMessage();
            $result = $returnResults ? [] : false;
        }

        return $result;
    }
}
<?php
namespace ERP\Core\Base\DataBase;

use ERPIA\Core\Translator;

abstract class DataBaseEngine
{
    /**
     * Contains the translator.
     *
     * @var Translator
     */
    protected $i18n;

    /**
     * Last error message.
     *
     * @var string
     */
    protected $lastErrorMsg = '';

    /**
     * Starts a transaction with the $link connection
     *
     * @param mixed $link
     */
    abstract public function beginTransaction($link);

    /**
     * Cast the column to a number
     *
     * @param mixed $link
     * @param string $column
     */
    abstract public function castInteger($link, $column);

    /**
     * Closes the connection to the database
     *
     * @param mixed $link
     */
    abstract public function close($link);

    /**
     * Converts the sqlColumns returned data to a working structure
     *
     * @param array $colData
     */
    abstract public function columnFromData($colData);

    /**
     * Commits operations done in the connection since beginTransaction
     *
     * @param mixed $link
     */
    abstract public function commit($link);

    /**
     * Connects to the database
     *
     * @param string $error
     */
    abstract public function connect(&$error);

    /**
     * Last generated error message in a database operation
     *
     * @param mixed $link
     */
    abstract public function errorMessage($link);

    /**
     * Escape the given column name
     *
     * @param mixed $link
     * @param string $name
     */
    abstract public function escapeColumn($link, $name);

    /**
     * Escape the given string
     *
     * @param mixed $link
     * @param string $str
     */
    abstract public function escapeString($link, $str);

    /**
     * Runs a DDL statement on the connection.
     * If there is no open transaction, it will create one and end it after the DDL
     *
     * @param mixed $link
     * @param string $sql
     *
     * @return bool
     */
    abstract public function exec($link, $sql);

    /**
     * Returns the link to the engine's SQL class
     *
     * @return DataBaseQueries
     */
    abstract public function getSQL();

    /**
     * Indicates if the connection has an open transaction
     *
     * @param mixed $link
     */
    abstract public function inTransaction($link);

    /**
     * List the existing tables in the connection
     *
     * @param mixed $link
     */
    abstract public function listTables($link);

    /**
     * Rolls back operations done in the connection since beginTransaction
     *
     * @param mixed $link
     */
    abstract public function rollback($link);

    /**
     * Runs a database statement on the connection
     *
     * @param mixed $link
     * @param string $sql
     *
     * @return array
     */
    abstract public function select($link, $sql);

    /**
     * Database engine information
     *
     * @param mixed $link
     *
     * @return string
     */
    abstract public function version($link): string;

    public function __construct()
    {
        $this->i18n = new Translator();
    }

    public function clearError(): void
    {
        $this->lastErrorMsg = '';
    }

    /**
     * Compares the data types from a numeric column. Returns true if they are equal
     *
     * @param string $dbType
     * @param string $xmlType
     *
     * @return bool
     */
    public function compareDataTypes($dbType, $xmlType): bool
    {
        if ($dbType === $xmlType) {
            return true;
        }

        if (strtolower($xmlType) === 'serial') {
            return true;
        }

        if (substr($dbType, 0, 4) === 'time' && substr($xmlType, 0, 4) === 'time') {
            return true;
        }

        return false;
    }

    /**
     * Returns the date format from the database engine
     *
     * @return string
     */
    public function dateStyle(): string
    {
        return 'Y-m-d';
    }

    /**
     * Returns the time format from the database engine
     *
     * @return string
     */
    public function timeStyle(): string
    {
        return 'H:i:s';
    }

    /**
     * Returns the datetime format from the database engine
     *
     * @return string
     */
    public function dateTimeStyle(): string
    {
        return 'Y-m-d H:i:s';
    }

    /**
     * Indicates the operator for the database engine.
     *
     * @param string $operator
     *
     * @return string
     */
    public function getOperator($operator): string
    {
        return $operator;
    }

    public function getLastError(): string
    {
        return $this->lastErrorMsg;
    }

    public function hasError(): bool
    {
        return $this->lastErrorMsg !== '';
    }

    /**
     * Sets the last error message
     *
     * @param string $message
     */
    public function setError(string $message): void
    {
        $this->lastErrorMsg = $message;
    }

    /**
     * Updates the sequence for auto-increment columns
     *
     * @param mixed $link
     * @param string $tableName
     * @param array $fields
     */
    public function updateSequence($link, $tableName, $fields): void
    {
        // Default implementation - specific database engines should override
    }

    /**
     * Checks if a table exists in the database
     *
     * @param mixed $link
     * @param string $tableName
     * @return bool
     */
    public function tableExists($link, $tableName): bool
    {
        $tables = $this->listTables($link);
        return in_array($tableName, $tables, true);
    }

    /**
     * Returns the SQL for limiting results
     *
     * @param int $offset
     * @param int $limit
     * @return string
     */
    public function getLimitSQL(int $offset = 0, int $limit = 50): string
    {
        return "LIMIT $limit OFFSET $offset";
    }

    /**
     * Returns the SQL for ordering results
     *
     * @param string $field
     * @param string $direction
     * @return string
     */
    public function getOrderBySQL(string $field, string $direction = 'ASC'): string
    {
        $direction = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';
        return "ORDER BY $field $direction";
    }

    /**
     * Returns the SQL for case-insensitive like
     *
     * @param string $field
     * @param string $value
     * @return string
     */
    public function getILikeSQL(string $field, string $value): string
    {
        return "$field ILIKE '$value'";
    }

    /**
     * Returns true if the database supports transactions
     *
     * @return bool
     */
    public function supportsTransactions(): bool
    {
        return true;
    }

    /**
     * Returns true if the database supports full text search
     *
     * @return bool
     */
    public function supportsFullTextSearch(): bool
    {
        return false;
    }

    /**
     * Returns the SQL for full text search if supported
     *
     * @param string $field
     * @param string $search
     * @return string
     */
    public function getFullTextSearchSQL(string $field, string $search): string
    {
        return "$field LIKE '%$search%'";
    }

    /**
     * Returns the SQL for creating an index
     *
     * @param string $indexName
     * @param string $tableName
     * @param array $columns
     * @param bool $unique
     * @return string
     */
    public function getCreateIndexSQL(string $indexName, string $tableName, array $columns, bool $unique = false): string
    {
        $uniqueStr = $unique ? 'UNIQUE ' : '';
        $columnsStr = implode(', ', $columns);
        return "CREATE {$uniqueStr}INDEX $indexName ON $tableName ($columnsStr)";
    }

    /**
     * Returns the SQL for dropping an index
     *
     * @param string $indexName
     * @param string $tableName
     * @return string
     */
    public function getDropIndexSQL(string $indexName, string $tableName): string
    {
        return "DROP INDEX $indexName";
    }

    /**
     * Prepares a value for use in SQL queries
     *
     * @param mixed $value
     * @return string
     */
    public function prepareValue($value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if (is_numeric($value)) {
            return (string)$value;
        }

        if (is_string($value)) {
            return "'" . addslashes($value) . "'";
        }

        return "'" . addslashes((string)$value) . "'";
    }
}
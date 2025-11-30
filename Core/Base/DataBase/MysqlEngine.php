<?php
namespace ERP\Core\Base\DataBase;

use Exception;
use ERPIA\Core\KernelException;
use mysqli;

class MysqlEngine extends DataBaseEngine
{
    private $transactions = [];
    private $utilsSQL;

    public function __construct()
    {
        parent::__construct();
        $this->utilsSQL = new MysqlQueries();
    }

    public function __destruct()
    {
        $this->rollbackPendingTransactions();
    }

    public function beginTransaction($link): bool
    {
        $success = $this->exec($link, 'START TRANSACTION;');
        if ($success) {
            $this->transactions[] = $link;
        }
        return $success;
    }

    public function castInteger($link, $column): string
    {
        return 'CAST(' . $this->escapeColumn($link, $column) . ' AS UNSIGNED)';
    }

    public function close($link): bool
    {
        $this->rollbackPendingTransactions();
        return $link->close();
    }

    public function columnFromData($colData): array
    {
        $lowercaseData = array_change_key_case($colData);
        return [
            'name' => $lowercaseData['field'],
            'type' => $lowercaseData['type'],
            'default' => $lowercaseData['default'],
            'is_nullable' => $lowercaseData['null'],
            'character_maximum_length' => $this->extractLength($lowercaseData['type'])
        ];
    }

    public function commit($link): bool
    {
        $success = $this->exec($link, 'COMMIT;');
        if ($success) {
            $this->removeTransaction($link);
        }
        return $success;
    }

    public function compareDataTypes($dbType, $xmlType): bool
    {
        if (parent::compareDataTypes($dbType, $xmlType)) {
            return true;
        }

        $typeMappings = [
            'tinyint(1)' => 'boolean',
            'int' => 'integer',
            'integer' => 'integer',
            'double' => 'double precision',
            'float' => 'real'
        ];

        if (isset($typeMappings[$dbType]) && $typeMappings[$dbType] === $xmlType) {
            return true;
        }

        if (strpos($dbType, 'int') === 0 && strtolower($xmlType) === 'integer') {
            return true;
        }

        if (strpos($dbType, 'varchar(') === 0 && strpos($xmlType, 'character varying(') === 0) {
            return $this->compareStringLengths($dbType, $xmlType);
        }

        return false;
    }

    public function connect(&$error)
    {
        if (!extension_loaded('mysqli')) {
            $error = $this->i18n->trans('mysql-extension-not-found');
            throw new KernelException('DatabaseConnectionError', $error);
        }

        $host = defined('ERP_DB_HOST') ? ERP_DB_HOST : 'localhost';
        $user = defined('ERP_DB_USER') ? ERP_DB_USER : 'root';
        $pass = defined('ERP_DB_PASS') ? ERP_DB_PASS : '';
        $name = defined('ERP_DB_NAME') ? ERP_DB_NAME : 'erp_system';
        $port = defined('ERP_DB_PORT') ? (int)ERP_DB_PORT : 3306;

        $connection = new mysqli($host, $user, $pass, $name, $port);
        
        if ($connection->connect_errno) {
            $error = $connection->connect_error;
            $this->setError($error);
            throw new KernelException('DatabaseConnectionError', $error);
        }

        $charset = defined('ERP_MYSQL_CHARSET') ? ERP_MYSQL_CHARSET : 'utf8mb4';
        $connection->set_charset($charset);
        $connection->autocommit(false);

        if (defined('ERP_DB_FOREIGN_KEYS') && !ERP_DB_FOREIGN_KEYS) {
            $this->exec($connection, 'SET foreign_key_checks = 0;');
        }

        return $connection;
    }

    public function errorMessage($link): string
    {
        return $link->error ?: $this->getLastError();
    }

    public function escapeColumn($link, $name): string
    {
        if (strpos($name, '.') !== false) {
            $parts = explode('.', $name);
            return '`' . implode('`.`', $parts) . '`';
        }
        return '`' . $name . '`';
    }

    public function escapeString($link, $str): string
    {
        return $link->real_escape_string($str);
    }

    public function exec($link, $sql): bool
    {
        try {
            if ($link->multi_query($sql)) {
                while ($link->more_results() && $link->next_result()) {
                    $link->store_result();
                }
            }

            if ($link->errno) {
                $this->setError($link->error);
                return false;
            }

            return true;
        } catch (Exception $exception) {
            $this->setError($exception->getMessage());
            return false;
        }
    }

    public function getSQL()
    {
        return $this->utilsSQL;
    }

    public function inTransaction($link): bool
    {
        return in_array($link, $this->transactions, true);
    }

    public function listTables($link): array
    {
        $tables = [];
        $database = defined('ERP_DB_NAME') ? ERP_DB_NAME : 'erp_system';
        $results = $this->select($link, "SHOW TABLES FROM `$database`;");
        
        foreach ($results as $row) {
            $tableName = current($row);
            if ($tableName) {
                $tables[] = $tableName;
            }
        }
        
        return $tables;
    }

    public function rollback($link): bool
    {
        $success = $this->exec($link, 'ROLLBACK;');
        $this->removeTransaction($link);
        return $success;
    }

    public function select($link, $sql): array
    {
        $results = [];
        
        try {
            $queryResult = $link->query($sql);
            
            if ($queryResult instanceof \mysqli_result) {
                while ($row = $queryResult->fetch_assoc()) {
                    $results[] = $row;
                }
                $queryResult->free();
            }
            
            if ($link->errno) {
                $this->setError($link->error);
            }
        } catch (Exception $exception) {
            $this->setError($exception->getMessage());
        }
        
        return $results;
    }

    public function version($link): string
    {
        return 'MySQL ' . $link->server_version;
    }

    private function extractLength($typeDefinition): ?int
    {
        if (preg_match('/\((\d+)\)/', $typeDefinition, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }

    private function compareStringLengths($mysqlType, $postgresType): bool
    {
        preg_match('/\((\d+)\)/', $mysqlType, $mysqlMatches);
        preg_match('/\((\d+)\)/', $postgresType, $pgMatches);
        
        return isset($mysqlMatches[1], $pgMatches[1]) && 
               $mysqlMatches[1] === $pgMatches[1];
    }

    private function rollbackPendingTransactions(): void
    {
        foreach ($this->transactions as $transaction) {
            $this->rollback($transaction);
        }
        $this->transactions = [];
    }

    private function removeTransaction($link): void
    {
        $index = array_search($link, $this->transactions, true);
        if ($index !== false) {
            array_splice($this->transactions, $index, 1);
        }
    }
}
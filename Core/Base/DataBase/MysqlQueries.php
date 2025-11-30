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

namespace ERPIA\Core\Base\DataBase;

/**
 * Class that implements MySQL-specific SQL query generation
 *
 * @author ERPIA Contributors
 */
class MysqlQueries implements DataBaseQueries
{

    /**
     * Converts a column value to integer using MySQL CAST
     */
    public function sql2Int(string $colName): string
    {
        return 'CAST(' . $colName . ' AS UNSIGNED INTEGER)';
    }

    /**
     * Generates SQL to add a constraint to a table
     */
    public function sqlAddConstraint(string $tableName, string $constraintName, string $sql): string
    {
        return 'ALTER TABLE ' . $tableName . ' ADD CONSTRAINT ' . $constraintName 
            . ' ' . $this->cleanPostgresSyntax($sql) . ';';
    }

    /**
     * Generates SQL to add a new column to a table
     */
    public function sqlAlterAddColumn(string $tableName, array $colData): string
    {
        return 'ALTER TABLE ' . $tableName . ' ADD `' . $colData['name'] . '` '
            . $this->buildColumnDefinition($colData) . ';';
    }

    /**
     * Generates SQL to alter a column's default value
     */
    public function sqlAlterColumnDefault(string $tableName, array $colData): string
    {
        return $colData['type'] === 'serial' ? '' : $this->sqlAlterModifyColumn($tableName, $colData);
    }

    /**
     * Generates SQL to create an index
     */
    public function sqlAddIndex(string $tableName, string $indexName, string $columns): string
    {
        return 'CREATE INDEX ' . $indexName . ' ON ' . $tableName . ' (' . $columns . ');';
    }

    /**
     * Generates SQL to alter a column's null constraint
     */
    public function sqlAlterColumnNull(string $tableName, array $colData): string
    {
        return $this->sqlAlterModifyColumn($tableName, $colData);
    }

    /**
     * Generates SQL to modify an existing column
     */
    public function sqlAlterModifyColumn(string $tableName, array $colData): string
    {
        $sql = 'ALTER TABLE ' . $tableName . ' MODIFY `' . $colData['name'] . '` '
            . $this->buildColumnDefinition($colData) . ';';

        return $this->cleanPostgresSyntax($sql);
    }

    /**
     * Generates SQL to show table columns
     */
    public function sqlColumns(string $tableName): string
    {
        return 'DESCRIBE `' . $tableName . '`;';
    }

    /**
     * Generates SQL to list table constraints
     */
    public function sqlConstraints(string $tableName): string
    {
        return 'SELECT CONSTRAINT_NAME AS name, CONSTRAINT_TYPE AS type'
            . ' FROM information_schema.table_constraints'
            . ' WHERE table_schema = DATABASE()'
            . " AND table_name = '" . $tableName . "';";
    }

    /**
     * Generates SQL to list extended constraint information
     */
    public function sqlConstraintsExtended(string $tableName): string
    {
        return 'SELECT tc.CONSTRAINT_NAME AS name,'
            . ' tc.CONSTRAINT_TYPE AS type,'
            . ' kcu.COLUMN_NAME AS column_name,'
            . ' kcu.REFERENCED_TABLE_NAME AS foreign_table_name,'
            . ' kcu.REFERENCED_COLUMN_NAME AS foreign_column_name,'
            . ' rc.UPDATE_RULE AS on_update,'
            . ' rc.DELETE_RULE AS on_delete'
            . ' FROM information_schema.TABLE_CONSTRAINTS tc'
            . ' LEFT JOIN information_schema.KEY_COLUMN_USAGE kcu'
            . ' ON tc.CONSTRAINT_SCHEMA = kcu.CONSTRAINT_SCHEMA'
            . ' AND tc.CONSTRAINT_NAME = kcu.CONSTRAINT_NAME'
            . ' LEFT JOIN information_schema.REFERENTIAL_CONSTRAINTS rc'
            . ' ON rc.CONSTRAINT_SCHEMA = tc.CONSTRAINT_SCHEMA'
            . ' AND rc.CONSTRAINT_NAME = tc.CONSTRAINT_NAME'
            . ' WHERE tc.TABLE_SCHEMA = DATABASE()'
            . " AND tc.TABLE_NAME = '" . $tableName . "'"
            . ' ORDER BY type DESC, name ASC;';
    }

    /**
     * Generates SQL to create a table
     */
    public function sqlCreateTable(string $tableName, array $columns, array $constraints, array $indexes): string
    {
        $columnDefinitions = [];
        foreach ($columns as $col) {
            $columnDefinitions[] = '`' . $col['name'] . '` ' . $this->buildColumnDefinition($col);
        }

        $charset = defined('ERPIA_MYSQL_CHARSET') ? ERPIA_MYSQL_CHARSET : 'utf8mb4';
        $collate = defined('ERPIA_MYSQL_COLLATE') ? ERPIA_MYSQL_COLLATE : 'utf8mb4_unicode_ci';
        
        $sql = 'CREATE TABLE ' . $tableName . ' (' . implode(', ', $columnDefinitions)
            . $this->generateTableConstraints($constraints) . ') '
            . 'ENGINE=InnoDB DEFAULT CHARSET=' . $charset . ' COLLATE=' . $collate . ';'
            . $this->generateTableIndexes($tableName, $indexes);

        return $this->cleanPostgresSyntax($sql);
    }

    /**
     * Generates SQL to drop a constraint
     */
    public function sqlDropConstraint(string $tableName, array $colData): string
    {
        $alterPrefix = 'ALTER TABLE ' . $tableName . ' DROP';
        switch ($colData['type']) {
            case 'FOREIGN KEY':
                return $alterPrefix . ' FOREIGN KEY ' . $colData['name'] . ';';
            case 'UNIQUE':
                return $alterPrefix . ' INDEX ' . $colData['name'] . ';';
            default:
                return '';
        }
    }

    /**
     * Generates SQL to drop an index
     */
    public function sqlDropIndex(string $tableName, array $colData): string
    {
        return 'DROP INDEX ' . $colData['name'] . ' ON ' . $tableName . ';';
    }

    /**
     * Generates SQL to drop a table
     */
    public function sqlDropTable(string $tableName): string
    {
        return 'DROP TABLE IF EXISTS ' . $tableName . ';';
    }

    /**
     * Generates SQL to show table indexes
     */
    public function sqlIndexes(string $tableName): string
    {
        return 'SHOW INDEX FROM ' . $tableName . ';';
    }

    /**
     * Generates SQL to get last inserted ID
     */
    public function sqlLastValue(): string
    {
        return 'SELECT LAST_INSERT_ID() AS num;';
    }

    /**
     * Generates SQL to rename a column
     */
    public function sqlRenameColumn(string $tableName, string $old_column, string $new_column): string
    {
        return 'ALTER TABLE ' . $tableName . ' CHANGE ' . $old_column . ' ' . $new_column;
    }

    /**
     * Generates table constraints SQL
     */
    public function sqlTableConstraints(array $xmlCons): string
    {
        $constraintSql = '';
        foreach ($xmlCons as $constraint) {
            $constraintValue = strtolower($constraint['constraint']);
            if (strpos($constraintValue, 'primary key') !== false || (defined('ERPIA_DB_FOREIGN_KEYS') && ERPIA_DB_FOREIGN_KEYS)) {
                $constraintSql .= ', CONSTRAINT ' . $constraint['name'] . ' ' . $constraint['constraint'];
            }
        }
        return $this->cleanPostgresSyntax($constraintSql);
    }

    /**
     * Generates table indexes SQL
     */
    private function generateTableIndexes(string $tableName, array $xmlIndexes): string
    {
        $indexSql = '';
        foreach ($xmlIndexes as $index) {
            $indexSql .= ' CREATE INDEX erpia_' . $index['name'] . ' ON ' . $tableName . ' (' . $index['columns'] . ');';
        }
        return $indexSql;
    }

    /**
     * Removes PostgreSQL-specific syntax
     */
    private function cleanPostgresSyntax(string $sql): string
    {
        $postgresPatterns = [
            '::character varying' => '',
            'without time zone' => '',
            'now()' => "'00:00'",
            'CURRENT_TIMESTAMP' => "'" . date('Y-m-d') . " 00:00:00'",
            'CURRENT_DATE' => date("'Y-m-d'")
        ];
        return str_replace(array_keys($postgresPatterns), array_values($postgresPatterns), $sql);
    }

    /**
     * Builds column constraints definition
     */
    private function buildColumnConstraints(array $colData): string
    {
        $constraints = '';
        $isNotNull = ($colData['null'] === 'NO');
        
        if ($isNotNull) {
            $constraints .= ' NOT NULL';
        } else {
            $constraints .= ' NULL';
        }

        $hasDefault = isset($colData['default']);
        if ($hasDefault && $colData['default'] === null && !$isNotNull) {
            $constraints .= ' DEFAULT NULL';
        } elseif ($hasDefault && $colData['default'] !== '') {
            $constraints .= ' DEFAULT ' . $colData['default'];
        }

        return $constraints;
    }

    /**
     * Builds complete column definition
     */
    private function buildColumnDefinition(array $colData): string
    {
        if ($colData['type'] === 'serial') {
            return 'INTEGER NOT NULL AUTO_INCREMENT';
        }
        
        return $colData['type'] . $this->buildColumnConstraints($colData);
    }

    /**
     * Generates table constraints for CREATE TABLE
     */
    private function generateTableConstraints(array $constraints): string
    {
        $constraintList = '';
        foreach ($constraints as $constraint) {
            $constraintType = strtolower($constraint['constraint']);
            if (strpos($constraintType, 'primary key') !== false || (defined('ERPIA_DB_FOREIGN_KEYS') && ERPIA_DB_FOREIGN_KEYS)) {
                $constraintList .= ', CONSTRAINT ' . $constraint['name'] . ' ' . $constraint['constraint'];
            }
        }
        return $constraintList;
    }
}
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

use ERPIA\Core\Config;

/**
 * Class that implements PostgreSQL-specific SQL query generation
 *
 * @author ERPIA Contributors
 */
class PostgresqlQueries implements DataBaseQueries
{
    /**
     * Converts column value to integer using PostgreSQL CAST
     */
    public function sql2Int(string $colName): string
    {
        return 'CAST(' . $colName . ' AS INTEGER)';
    }

    /**
     * Generates SQL to add a constraint to a table
     */
    public function sqlAddConstraint(string $tableName, string $constraintName, string $sql): string
    {
        $cleanedSql = str_replace('(user)', '("user")', $sql);
        return 'ALTER TABLE ' . $tableName . ' ADD CONSTRAINT ' . $constraintName . ' ' . $cleanedSql . ';';
    }

    /**
     * Generates SQL to add a new column to a table
     */
    public function sqlAlterAddColumn(string $tableName, array $colData): string
    {
        $sql = 'ALTER TABLE ' . $tableName . ' ADD COLUMN "' . $colData['name'] . '" ' . $colData['type'];

        if ($colData['type'] === 'serial') {
            $sequenceName = $tableName . '_' . $colData['name'] . '_seq';
            $sql .= " DEFAULT nextval('" . $sequenceName . "'::regclass)";
        } elseif (!empty($colData['default'])) {
            $sql .= ' DEFAULT ' . $colData['default'];
        }

        if (isset($colData['null']) && $colData['null'] === 'NO') {
            $sql .= ' NOT NULL';
        }

        return $sql . ';';
    }

    /**
     * Generates SQL to alter a column's default value
     */
    public function sqlAlterColumnDefault(string $tableName, array $colData): string
    {
        if ($colData['type'] === 'serial') {
            return '';
        }

        $action = empty($colData['default']) ? ' DROP DEFAULT' : ' SET DEFAULT ' . $colData['default'];
        return 'ALTER TABLE ' . $tableName . ' ALTER COLUMN "' . $colData['name'] . '"' . $action . ';';
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
        if ($colData['type'] === 'serial') {
            return '';
        }

        $action = isset($colData['null']) && $colData['null'] === 'YES' ? ' DROP NOT NULL' : ' SET NOT NULL';
        return 'ALTER TABLE ' . $tableName . ' ALTER COLUMN "' . $colData['name'] . '"' . $action . ';';
    }

    /**
     * Generates SQL to modify an existing column type
     */
    public function sqlAlterModifyColumn(string $tableName, array $colData): string
    {
        return 'ALTER TABLE ' . $tableName . ' ALTER COLUMN "' . $colData['name'] . '" TYPE ' . $colData['type'] . ';';
    }

    /**
     * Generates SQL to show table columns information
     */
    public function sqlColumns(string $tableName): string
    {
        $databaseName = Config::get('db_name');
        return "SELECT 
                column_name as name, 
                data_type as type,
                character_maximum_length,
                column_default as default,
                is_nullable
            FROM information_schema.columns
            WHERE table_catalog = '" . $databaseName . "'
            AND table_name = '" . $tableName . "'
            ORDER BY ordinal_position;";
    }

    /**
     * Generates SQL to list table constraints
     */
    public function sqlConstraints(string $tableName): string
    {
        return "SELECT 
                constraint_type as type, 
                constraint_name as name
            FROM information_schema.table_constraints
            WHERE table_name = '" . $tableName . "'
            AND constraint_type IN ('PRIMARY KEY', 'FOREIGN KEY', 'UNIQUE')
            ORDER BY type DESC, name ASC;";
    }

    /**
     * Generates SQL to list extended constraint information
     */
    public function sqlConstraintsExtended(string $tableName): string
    {
        return "SELECT 
                tc.constraint_type as type,
                tc.constraint_name as name,
                kcu.column_name,
                ccu.table_name AS foreign_table_name,
                ccu.column_name AS foreign_column_name,
                rc.update_rule AS on_update,
                rc.delete_rule AS on_delete
            FROM information_schema.table_constraints tc
            LEFT JOIN information_schema.key_column_usage kcu
                ON kcu.constraint_name = tc.constraint_name
                AND kcu.table_name = tc.table_name
            LEFT JOIN information_schema.constraint_column_usage ccu
                ON ccu.constraint_name = tc.constraint_name
            LEFT JOIN information_schema.referential_constraints rc
                ON rc.constraint_name = tc.constraint_name
            WHERE tc.table_name = '" . $tableName . "'
            AND tc.constraint_type IN ('PRIMARY KEY', 'FOREIGN KEY', 'UNIQUE')
            ORDER BY type DESC, name ASC;";
    }

    /**
     * Generates SQL to create a table
     */
    public function sqlCreateTable(string $tableName, array $columns, array $constraints, array $indexes): string
    {
        $columnDefinitions = [];
        $serialTypes = ['serial', 'bigserial'];

        foreach ($columns as $column) {
            $definition = '"' . $column['name'] . '" ' . $column['type'];

            // Handle NOT NULL constraint
            if (isset($column['null']) && $column['null'] === 'NO') {
                $definition .= ' NOT NULL';
            }

            // Handle DEFAULT values (skip for serial types)
            if (!in_array($column['type'], $serialTypes) && !empty($column['default'])) {
                $defaultValue = $column['default'] === null ? 'NULL' : $column['default'];
                $definition .= ' DEFAULT ' . $defaultValue;
            }

            $columnDefinitions[] = $definition;
        }

        $tableSql = 'CREATE TABLE ' . $tableName . ' (' . implode(', ', $columnDefinitions);
        $tableSql .= $this->generateTableConstraints($constraints) . ');';
        $tableSql .= $this->generateTableIndexes($tableName, $indexes);

        return $tableSql;
    }

    /**
     * Generates SQL to drop a constraint
     */
    public function sqlDropConstraint(string $tableName, array $colData): string
    {
        return 'ALTER TABLE ' . $tableName . ' DROP CONSTRAINT ' . $colData['name'] . ';';
    }

    /**
     * Generates SQL to drop an index
     */
    public function sqlDropIndex(string $tableName, array $colData): string
    {
        return 'DROP INDEX IF EXISTS ' . $colData['name'] . ';';
    }

    /**
     * Generates SQL to drop a table
     */
    public function sqlDropTable(string $tableName): string
    {
        return 'DROP TABLE IF EXISTS ' . $tableName . ' CASCADE;';
    }

    /**
     * Generates SQL to show table indexes
     */
    public function sqlIndexes(string $tableName): string
    {
        return "SELECT
                i.relname AS index_name,
                a.attname AS column_name,
                ix.indisunique AS is_unique
            FROM pg_class t
            JOIN pg_index ix ON t.oid = ix.indrelid
            JOIN pg_class i ON i.oid = ix.indexrelid
            JOIN pg_attribute a ON a.attrelid = t.oid AND a.attnum = ANY(ix.indkey)
            WHERE t.relname = '" . $tableName . "'
            AND t.relkind = 'r'
            ORDER BY i.relname, a.attname;";
    }

    /**
     * Generates SQL to get last inserted sequence value
     */
    public function sqlLastValue(): string
    {
        return 'SELECT lastval() AS num;';
    }

    /**
     * Generates SQL to rename a column
     */
    public function sqlRenameColumn(string $tableName, string $old_column, string $new_column): string
    {
        return 'ALTER TABLE ' . $tableName . ' RENAME COLUMN ' . $old_column . ' TO ' . $new_column . ';';
    }

    /**
     * Generates table constraints SQL for CREATE TABLE
     */
    public function sqlTableConstraints(array $xmlCons): string
    {
        $constraintsSql = '';

        foreach ($xmlCons as $constraint) {
            $constraintType = strtolower($constraint['constraint']);

            // Always include PRIMARY KEY constraints
            if (strpos($constraintType, 'primary key') !== false) {
                $constraintsSql .= ', ' . $constraint['constraint'];
                continue;
            }

            // Include FOREIGN KEY constraints based on configuration
            $foreignKeysEnabled = Config::get('db_foreign_keys', true);
            if ($foreignKeysEnabled || strpos($constraint['constraint'], 'FOREIGN KEY') !== 0) {
                $cleanedConstraint = str_replace('(user)', '("user")', $constraint['constraint']);
                $constraintsSql .= ', CONSTRAINT ' . $constraint['name'] . ' ' . $cleanedConstraint;
            }
        }

        return $constraintsSql;
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
     * Generates table constraints for CREATE TABLE
     */
    private function generateTableConstraints(array $constraints): string
    {
        $constraintList = '';
        foreach ($constraints as $constraint) {
            $constraintValue = strtolower($constraint['constraint']);
            
            if (strpos($constraintValue, 'primary key') !== false) {
                $constraintList .= ', ' . $constraint['constraint'];
                continue;
            }

            $foreignKeysEnabled = Config::get('db_foreign_keys', true);
            if ($foreignKeysEnabled || strpos($constraint['constraint'], 'FOREIGN KEY') !== 0) {
                $cleanConstraint = str_replace('(user)', '("user")', $constraint['constraint']);
                $constraintList .= ', CONSTRAINT ' . $constraint['name'] . ' ' . $cleanConstraint;
            }
        }
        return $constraintList;
    }
}
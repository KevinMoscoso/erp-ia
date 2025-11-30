<?php
namespace ERP\Core\Base\DataBase;

interface DataBaseQueries
{
    public function sql2Int(string $colName): string;

    public function sqlAddConstraint(string $tableName, string $constraintName, string $sql): string;

    public function sqlAlterAddColumn(string $tableName, array $colData): string;

    public function sqlAlterColumnDefault(string $tableName, array $colData): string;

    public function sqlAlterColumnNull(string $tableName, array $colData): string;

    public function sqlAlterModifyColumn(string $tableName, array $colData): string;

    public function sqlColumns(string $tableName): string;

    public function sqlConstraints(string $tableName): string;

    public function sqlConstraintsExtended(string $tableName): string;

    public function sqlCreateTable(string $tableName, array $columns, array $constraints, array $indexes): string;

    public function sqlDropConstraint(string $tableName, array $colData): string;

    public function sqlDropTable(string $tableName): string;

    public function sqlIndexes(string $tableName): string;

    public function sqlLastValue(): string;

    public function sqlRenameColumn(string $tableName, string $old_column, string $new_column): string;

    public function sqlTableConstraints(array $xmlCons): string;
}
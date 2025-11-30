<?php
namespace ERP\Core\Base\DataBase;

use ERPIA\Core\Base\DataBase;

class DataBaseWhere
{
    private $dataBase;
    public $fields;
    public $operation;
    public $operator;
    public $value;

    public function __construct(string $fields, $value, string $operator = '=', string $operation = 'AND')
    {
        $this->dataBase = new DataBase();
        $this->fields = $fields;
        $this->operation = $operation;
        $this->operator = $operator;
        $this->value = $value;

        if (null === $value && $operator === '=') {
            $this->operator = 'IS';
        } elseif (null === $value && $operator === '!=') {
            $this->operator = 'IS NOT';
        }
    }

    public static function applyOperation(string $fields): array
    {
        if (empty($fields)) {
            return [];
        }

        $result = [];
        foreach (explode(',', $fields) as $field) {
            if ($field !== '' && strpos($field, '|') === false) {
                $result[$field] = 'AND';
            }
        }
        foreach (explode('|', $fields) as $field) {
            if ($field !== '' && strpos($field, ',') === false) {
                $result[$field] = 'OR';
            }
        }

        return $result;
    }

    public static function getFieldsFilter(array $whereItems): array
    {
        $result = [];
        foreach ($whereItems as $item) {
            if ($item->operator !== '=') {
                continue;
            }

            $fields = explode('|', $item->fields);
            foreach ($fields as $field) {
                $result[$field] = $item->value;
            }
        }

        return $result;
    }

    public function getSQLWhereItem(bool $applyOperation = false, string $prefix = ''): string
    {
        $fields = explode('|', $this->fields);
        $result = $this->applyValueToFields($this->value, $fields);
        if ($result === '') {
            return '';
        }

        if (count($fields) > 1) {
            $result = '(' . $result . ')';
        }

        $result = $prefix . $result;
        if ($applyOperation) {
            $result = ' ' . $this->operation . ' ' . $result;
        }

        return $result;
    }

    public static function getSQLWhere(array $whereItems): string
    {
        $result = '';
        $join = false;
        $group = false;

        $keys = array_keys($whereItems);
        foreach ($keys as $num => $key) {
            $next = isset($keys[$num + 1]) ? $keys[$num + 1] : null;

            $prefix = is_null($next) ? '' : self::getGroupPrefix($whereItems[$next], $group);

            $result .= $whereItems[$key]->getSQLWhereItem($join, $prefix);
            $join = true;

            if (null !== $next && $group && $whereItems[$next]->operation != 'OR') {
                $result .= ')';
                $group = false;
            }
        }

        if ($result === '') {
            return '';
        }

        if ($group == true) {
            $result .= ')';
        }

        return ' WHERE ' . $result;
    }

    private function applyValueToFields($value, array $fields): string
    {
        $result = '';
        foreach ($fields as $field) {
            $union = empty($result) ? '' : ' OR ';
            switch ($this->operator) {
                case 'LIKE':
                    $result .= $union . 'LOWER(' . $this->escapeColumn($field) . ') '
                        . $this->dataBase->getOperator($this->operator) . ' ' . $this->getValueFromOperatorLike($value);
                    break;

                case 'XLIKE':
                    $result .= $union . '(';
                    $union2 = '';
                    foreach (explode(' ', $value) as $query) {
                        $result .= $union2 . 'LOWER(' . $this->escapeColumn($field) . ') '
                            . $this->dataBase->getOperator('LIKE') . ' ' . $this->getValueFromOperatorLike($query);
                        $union2 = ' AND ';
                    }
                    $result .= ')';
                    break;

                default:
                    $result .= $union . $this->escapeColumn($field) . ' '
                        . $this->dataBase->getOperator($this->operator) . ' ' . $this->getValue($value);
                    break;
            }
        }

        return $result;
    }

    private function escapeColumn(string $column): string
    {
        $exclude = ['.', 'CAST('];
        foreach ($exclude as $char) {
            if (strpos($column, $char) !== false) {
                return $column;
            }
        }

        return $this->dataBase->escapeColumn($column);
    }

    private static function getGroupPrefix(DataBaseWhere $item, bool &$group): string
    {
        if ($item->operation == 'OR' && $group === false) {
            $group = true;
            return '(';
        }

        return '';
    }

    private function getValueFromOperatorIn($values): string
    {
        if (is_array($values)) {
            $result = '';
            $comma = '';
            foreach ($values as $value) {
                $result .= $comma . $this->dataBase->var2str($value);
                $comma = ',';
            }
            return $result;
        }

        if (0 === stripos($values, 'select ')) {
            return $values;
        }

        $result = '';
        $comma = '';
        foreach (explode(',', $values) as $value) {
            $result .= $comma . $this->dataBase->var2str($value);
            $comma = ',';
        }
        return $result;
    }

    private function getValueFromOperatorLike($value): string
    {
        if (is_null($value) || is_bool($value)) {
            return $this->dataBase->var2str($value);
        }

        if (strpos($value, '%') === false) {
            return "LOWER('%" . $this->dataBase->escapeString($value) . "%')";
        }

        return "LOWER('" . $this->dataBase->escapeString($value) . "')";
    }

    private function getValueFromOperator($value): string
    {
        switch ($this->operator) {
            case 'IN':
            case 'NOT IN':
                return '(' . $this->getValueFromOperatorIn($value) . ')';

            case 'LIKE':
            case 'XLIKE':
                return $this->getValueFromOperatorLike($value);

            default:
                return $this->dataBase->var2str($value);
        }
    }

    private function getValue($value): string
    {
        if (in_array($this->operator, ['IN', 'LIKE', 'NOT IN', 'XLIKE'], false)) {
            return $this->getValueFromOperator($value);
        }

        if ($value !== null && strpos($value, 'field:') === 0 && strlen($value) > 6) {
            return $this->dataBase->escapeColumn(substr($value, 6));
        }

        return $this->dataBase->var2str($value);
    }
}
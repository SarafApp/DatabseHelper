<?php

namespace Saraf\DatabaseHelper;

use React\MySQL\ConnectionInterface;
use React\MySQL\QueryResult;
use React\Promise\Promise;
use React\Promise\PromiseInterface;

class DatabaseHelper
{
    protected ConnectionInterface $connection;

    public function __construct(ConnectionInterface $connection)
    {
        $this->connection = $connection;
    }

    public function select(string $tableName, array $selectWhere = [], array $selections = [], $moreSettings = null, $leftJoins = null, bool $escape = true): PromiseInterface
    {
        if (!is_null($selections) && count($selections) > 0) {
            $selects = ($escape)
                ? $this->escape(implode(",", $selections))
                : implode(",", $selections);

            $query = "SELECT $selects FROM $tableName";
        } else {
            $query = "SELECT * FROM $tableName";
        }

        $query .= is_null($leftJoins)
            ? " WHERE "
            : " $leftJoins WHERE ";

        if (!is_null($selectWhere) && count($selectWhere) > 0) {
            $selectWhere = ($escape)
                ? $this->escapeArray($selectWhere)
                : $selectWhere;

            foreach ($selectWhere as $wName => $wValue) {
                $query .= (is_array($wValue))
                    ? "$wName IN ('" . implode("','", $wValue) . "') AND "
                    : "$wName = '$wValue' AND ";
            }
            $query = substr($query, 0, -5);
        } else {
            $query .= "1";
        }

        if (!is_null($moreSettings))
            $query .= " $moreSettings";

        return $this->query($query);
    }

    public function insert(string $tableName, array $insertArray, bool $escape = true): PromiseInterface
    {
        $keys = ($escape)
            ? $this->escapeArray(array_keys($insertArray))
            : array_keys($insertArray);

        $vals = ($escape)
            ? $this->escapeArray(array_values($insertArray))
            : array_values($insertArray);

        $query = sprintf(
            "INSERT INTO %s (%s) VALUES (%s);",
            $tableName,
            implode(', ', $keys),
            implode(',', $vals));

        return $this->query($query);
    }

    public function multiInsert(string $tableName, array $columnNames, array $insertArrays, bool $escape = true): PromiseInterface
    {
        $columnNames = ($escape)
            ? $this->escapeArray($columnNames)
            : $columnNames;

        $insertArrays = ($escape)
            ? $this->escapeArray(array_keys($insertArrays))
            : array_keys($insertArrays);

        $inserts = "";
        foreach ($insertArrays as $insertArray)
            $inserts .= sprintf(
                "(%s),",
                implode(', ', $insertArray));

        $inserts = substr($inserts, 0, -1);

        $query = sprintf(
            "INSERT INTO %s (%s) VALUES %s",
            $tableName,
            implode(', ', $columnNames),
            $inserts);

        return $this->query($query);
    }

    public function insertUpdate(string $tableName, array $insertArray, array $updateArray, bool $escape = true): PromiseInterface
    {
        if (count($updateArray) == 0 || count($insertArray) == 0)
            return new Promise(function (callable $resolve) {
                $resolve([
                    'result' => false,
                    'error' => "Param Error"
                ]);
            });

        $keys = ($escape)
            ? $this->escapeArray(array_keys($insertArray))
            : array_keys($insertArray);

        $vals = ($escape)
            ? $this->escapeArray(array_values($insertArray))
            : array_values($insertArray);

        $updateArray = ($escape)
            ? $this->escapeArray($updateArray)
            : $updateArray;

        $appendValueClause = "";
        foreach ($vals as $val)
            $appendValueClause .= is_null($val)
                ? "NULL,"
                : "'$val',";

        $appendValueClause = substr($appendValueClause, 0, -1);

        $query = sprintf(
            "INSERT INTO %s (%s) VALUES (%s) ON DUPLICATE KEY UPDATE ",
            $tableName,
            implode(', ', $keys),
            $appendValueClause);

        foreach ($updateArray as $key => $value)
            $query .= is_null($value)
                ? "$key = null,"
                : "$key = '$value',";

        $query = substr($query, 0, -1);
        return $this->query($query);
    }

    public function multiInsertUpdate(string $tableName, array $columnNames, array $insertArrays, string $aliasName, array $updateArray, bool $escape = true): PromiseInterface
    {
        if (count($updateArray) == 0 || count($insertArrays) == 0)
            return new Promise(function (callable $resolve) {
                $resolve([
                    'result' => false,
                    'error' => "Param Error"
                ]);
            });

        $columnNames = ($escape)
            ? $this->escapeArray($columnNames)
            : $columnNames;

        $insertArrays = ($escape)
            ? $this->escapeArray($insertArrays)
            : $insertArrays;

        $updateArray = ($escape)
            ? $this->escapeArray($updateArray)
            : $updateArray;

        $query = sprintf(
            "INSERT INTO %s (%s) VALUES ",
            $tableName,
            implode(', ', $columnNames));

        foreach ($insertArrays as $insertArray) {
            $appendValueClause = "";

            foreach ($insertArray as $insertValue) {
                if (is_null($insertValue)) {
                    $appendValueClause .= "NULL,";
                } else if (str_starts_with($insertValue, "IF(") && str_ends_with($insertValue, ")")) {
                    $appendValueClause .= "$insertValue,";
                } else {
                    $appendValueClause .= "'$insertValue',";
                }
            }

            $appendValueClause = substr($appendValueClause, 0, -1);
            $query .= "(" . $appendValueClause . "),";
        }

        $query = substr($query, 0, -1);

        $query .= " AS $aliasName ON DUPLICATE KEY UPDATE ";

        foreach ($updateArray as $key => $value) {
            if ($value == null) {
                $query .= "$key = null,";
            } else if (str_starts_with($value, "IF(") && str_ends_with($value, ")")) {
                $query .= "$key = $value,";
            } else {
                $query .= "$key = '$value',";
            }
        }

        $query = substr($query, 0, -1);
        return $this->query($query);
    }

    public function update(string $tableName, array $updateArray, array $whereArray, bool $escape = true): PromiseInterface
    {
        if (count($updateArray) == 0 || count($whereArray) == 0)
            return new Promise(function (callable $resolve) {
                $resolve([
                    'result' => false,
                    'error' => "Param Error"
                ]);
            });

        $updateArray = ($escape)
            ? $this->escapeArray($updateArray)
            : $updateArray;

        $whereArray = ($escape)
            ? $this->escapeArray($whereArray)
            : $whereArray;

        $query = "UPDATE $tableName SET ";

        foreach ($updateArray as $updateKey => $updateValue)
            $query .= (is_null($updateValue))
                ? "$updateKey = null,"
                : "$updateKey = '$updateValue',";

        $query = substr($query, 0, -1);
        $query .= " WHERE ";

        foreach ($whereArray as $whereKey => $whereValue)
            $query .= is_array($whereValue)
                ? "$whereKey IN ('" . implode("','", $whereValue) . "') AND "
                : "$whereKey = '$whereValue' AND ";

        $query = substr($query, 0, -5);
        return $this->query($query);
    }

    public function query($query): PromiseInterface
    {
        return $this->connection
            ->query($query)
            ->then(function (QueryResult $result) {
                if (!is_null($result->resultRows)) {
                    return [
                        'result' => true,
                        'count' => count($result->resultRows),
                        'rows' => $result->resultRows
                    ];
                } else {
                    $res = [
                        'result' => true,
                        'affectedRows' => $result->affectedRows
                    ];
                    if ($result->insertId !== 0)
                        $res['insertId'] = $result->insertId;

                    return $res;
                }
            }, function (\Exception $error) {
                return [
                    'result' => false,
                    'line' => $error->getLine(),
                    'error' => $error->getMessage(),
                    'details' => $error->getTraceAsString(),
                ];
            });
    }

    public function escape($char): string|null
    {
        if (is_null($char))
            return null;

        $search = array("\\", "\x00", "\n", "\r", "'", '"', "\x1a");
        $replace = array("\\\\", "\\0", "\\n", "\\r", "\'", '\"', "\\Z");

        return str_replace($search, $replace, $char);
    }

    public function escapeArray($array): array
    {
        $newArray = [];
        foreach ($array as $itemName => $itemValue) {
            $newArray[$this->escape($itemName)] = (is_array($itemValue))
                ? array_map(function ($e) {
                    return $this->escape($e);
                }, $itemValue)
                : $this->escape($itemValue);
        }
        return $newArray;
    }

}
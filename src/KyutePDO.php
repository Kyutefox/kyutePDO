<?php

namespace Kyutefox\KyutePDO;

use PDO;
use Closure;
use PDOException;
use Kyutefox\KyutePDO\cache\Cache;
use Kyutefox\KyutePDO\Config\Connection;
use Kyutefox\KyutePDO\Interfaces\KyutePDOInterface;

class KyutePDO implements KyutePDOInterface
{

    /**
     * @var PDO|null
     */

    public $pdo = null;
    protected $select   = '*';
    protected $from     = null;
    protected $where    = null;
    protected $limit    = null;
    protected $offset   = null;
    protected $join     = null;
    protected $orderBy  = null;
    protected $groupBy  = null;
    protected $having   = null;
    protected $grouped  = false;
    protected $numRows  = 0;
    protected $insertId = null;
    protected $query    = null;
    protected $error    = null;
    protected $result   = [];
    protected $prefix   = null;

    protected $operators = ['=', '!=', '<', '>', '<=', '>=', '<>'];

    protected $queryCount = 0;
    protected $transactionCount = 0;
    protected $debug = true;
    protected $cache = null;
    protected $cacheDir = null;

    /**
     * @param array $config
     */
    public function __construct(array $config)
    {
        $connection     = new Connection($config);
        $this->prefix   = !empty($config["db_prefix"])   ? $config["db_prefix"]  : "";
        $this->debug    = !empty($config['debug'])       ? $config['debug']      : true;
        $this->cacheDir = !empty($config['cache_dir'])   ? $config['cache_dir']  : __DIR__ . '/cache/';
        $this->pdo      = $connection->Init();
        return $this->pdo;
    }

    /**
     * @param $table
     * @return $this
     */

    public function table($table): KyutePDO
    {

        if (is_array($table)) {
            $from = '';
            foreach ($table as $key) {
                $from .= $this->prefix . $key . ', ';
            }
            $this->from = rtrim($from, ', ');
        } else {
            if (strpos($table, ', ') > 0) {
                $table = explode(',', $table);
                foreach ($table as $key => $value) {
                    $value = $this->prefix . ltrim($value);
                }
                $this->from = implode(', ', $table);
            } else {
                $this->from = $this->prefix . $table;
            }
        }
        return $this;
    }

    /**
     * @param $fields
     * @return $this
     */

    public function select($fields): KyutePDO
    {
        $select = is_array($fields) ? implode(', ', $fields) : $fields;
        $this->optimizeSelect($select);
        return $this;
    }

    /**
     * @param $field
     * @param null $name
     * @return $this
     */

    public function max($field, $name = null): KyutePDO
    {
        $column = 'MAX(' . $field . ')' . (!is_null($name) ? ' AS ' . $name : '');
        $this->optimizeSelect($column);
        return $this;
    }

    /**
     * @param $field
     * @param null $name
     * @return $this
     */

    public function min($field, $name = null): KyutePDO
    {
        $column = 'MIN(' . $field . ')' . (!is_null($name) ? ' AS ' . $name : '');
        $this->optimizeSelect($column);
        return $this;
    }

    /**
     * @param $field
     * @param null $name
     * @return $this
     */

    public function sum($field, $name = null): KyutePDO
    {
        $column = 'SUM(' . $field . ')' . (!is_null($name) ? ' AS ' . $name : '');
        $this->optimizeSelect($column);
        return $this;
    }

    /**
     * @param $field
     * @param null $name
     * @return $this
     */

    public function count($field, $name = null): KyutePDO
    {
        $column = 'COUNT(' . $field . ')' . (!is_null($name) ? ' AS ' . $name : '');
        $this->optimizeSelect($column);
        return $this;
    }

    /**
     * @param $field
     * @param null $name
     * @return $this
     */

    public function avg($field, $name = null): KyutePDO
    {
        $column = 'AVG(' . $field . ')' . (!is_null($name) ? ' AS ' . $name : '');
        $this->optimizeSelect($column);
        return $this;
    }

    /**
     * @param $table
     * @param null $field1
     * @param null $operator
     * @param null $field2
     * @param string $type
     * @return $this
     */

    public function join($table, $field1 = null, $operator = null, $field2 = null, $type = ''): KyutePDO
    {
        $on = $field1;
        $table = $this->prefix . $table;

        if (!is_null($operator)) {
            $on = !in_array($operator, $this->operators)
                ? $field1 . ' = ' . $operator . (!is_null($field2) ? ' ' . $field2 : '')
                : $field1 . ' ' . $operator . ' ' . $field2;
        }

        $this->join = (is_null($this->join))
            ? ' ' . $type . 'JOIN' . ' ' . $table . ' ON ' . $on
            : $this->join . ' ' . $type . 'JOIN' . ' ' . $table . ' ON ' . $on;

        return $this;
    }

    /**
     * @param $table
     * @param $field1
     * @param string $operator
     * @param string $field2
     * @return $this
     */

    public function innerJoin($table, $field1, $operator = '', $field2 = ''): KyutePDO
    {
        return $this->join($table, $field1, $operator, $field2, 'INNER ');
    }

    /**
     * @param $table
     * @param $field1
     * @param string $operator
     * @param string $field2
     * @return $this
     */

    public function leftJoin($table, $field1, $operator = '', $field2 = ''): KyutePDO
    {
        return $this->join($table, $field1, $operator, $field2, 'LEFT ');
    }

    /**
     * @param $table
     * @param $field1
     * @param string $operator
     * @param string $field2
     * @return $this
     */

    public function rightJoin($table, $field1, $operator = '', $field2 = ''): KyutePDO
    {
        return $this->join($table, $field1, $operator, $field2, 'RIGHT ');
    }

    /**
     * @param $table
     * @param $field1
     * @param string $operator
     * @param string $field2
     * @return $this
     */

    public function fullOuterJoin($table, $field1, $operator = '', $field2 = ''): KyutePDO
    {
        return $this->join($table, $field1, $operator, $field2, 'FULL OUTER ');
    }

    /**
     * @param $table
     * @param $field1
     * @param string $operator
     * @param string $field2
     * @return $this
     */

    public function leftOuterJoin($table, $field1, $operator = '', $field2 = ''): KyutePDO
    {
        return $this->join($table, $field1, $operator, $field2, 'LEFT OUTER ');
    }

    /**
     * @param $table
     * @param $field1
     * @param string $operator
     * @param string $field2
     * @return $this
     */

    public function rightOuterJoin($table, $field1, $operator = '', $field2 = ''): KyutePDO
    {
        return $this->join($table, $field1, $operator, $field2, 'RIGHT OUTER ');
    }

    /**
     * @param $where
     * @param null $operator
     * @param null $val
     * @param string $type
     * @param string $andOr
     * @return $this
     */

    public function where($where, $operator = null, $val = null, $type = '', $andOr = 'AND'): KyutePDO
    {
        if (is_array($where) && !empty($where)) {
            $_where = [];
            foreach ($where as $column => $data) {
                $_where[] = $type . $column . '=' . $this->escape($data);
            }
            $where = implode(' ' . $andOr . ' ', $_where);
        } else {
            if (is_null($where) || empty($where)) {
                return $this;
            }

            if (is_array($operator)) {
                $params = explode('?', $where);
                $_where = '';
                foreach ($params as $key => $value) {
                    if (!empty($value)) {
                        $_where .= $type . $value . (isset($operator[$key]) ? $this->escape($operator[$key]) : '');
                    }
                }
                $where = $_where;
            } elseif (!in_array($operator, $this->operators) || $operator == false) {
                $where = $type . $where . ' = ' . $this->escape($operator);
            } else {
                $where = $type . $where . ' ' . $operator . ' ' . $this->escape($val);
            }
        }

        if ($this->grouped) {
            $where = '(' . $where;
            $this->grouped = false;
        }

        $this->where = is_null($this->where)
            ? $where
            : $this->where . ' ' . $andOr . ' ' . $where;

        return $this;
    }

    /**
     * @param $where
     * @param null $operator
     * @param null $val
     * @return $this
     */

    public function orWhere($where, $operator = null, $val = null): KyutePDO
    {
        return $this->where($where, $operator, $val, '', 'OR');
    }

    /**
     * @param $where
     * @param null $operator
     * @param null $val
     * @return $this
     */

    public function notWhere($where, $operator = null, $val = null): KyutePDO
    {
        return $this->where($where, $operator, $val, 'NOT ', 'AND');
    }

    /**
     * @param $where
     * @param null $operator
     * @param null $val
     * @return $this
     */

    public function orNotWhere($where, $operator = null, $val = null): KyutePDO
    {
        return $this->where($where, $operator, $val, 'NOT ', 'OR');
    }

    /**
     * @param $where
     * @param false $not
     * @return $this
     */

    public function whereNull($where, $not = false): KyutePDO
    {
        $where = $where . ' IS ' . ($not ? 'NOT' : '') . ' NULL';
        $this->where = is_null($this->where) ? $where : $this->where . ' ' . 'AND ' . $where;

        return $this;
    }

    /**
     * @param $where
     * @return $this
     */

    public function whereNotNull($where): KyutePDO
    {
        return $this->whereNull($where, true);
    }

    /**
     * @param Closure $obj
     * @return $this
     */

    public function grouped(Closure $obj): KyutePDO
    {
        $this->grouped = true;
        call_user_func_array($obj, [$this]);
        $this->where .= ')';

        return $this;
    }

    /**
     * @param $field
     * @param array $keys
     * @param string $type
     * @param string $andOr
     * @return $this
     */

    public function in($field, array $keys, $type = '', $andOr = 'AND'): KyutePDO
    {
        if (is_array($keys)) {
            $_keys = [];
            foreach ($keys as $k => $v) {
                $_keys[] = is_numeric($v) ? $v : $this->escape($v);
            }
            $where = $field . ' ' . $type . 'IN (' . implode(', ', $_keys) . ')';

            if ($this->grouped) {
                $where = '(' . $where;
                $this->grouped = false;
            }

            $this->where = is_null($this->where)
                ? $where
                : $this->where . ' ' . $andOr . ' ' . $where;
        }

        return $this;
    }

    /**
     * @param $field
     * @param array $keys
     * @return $this
     */

    public function notIn($field, array $keys): KyutePDO
    {
        return $this->in($field, $keys, 'NOT ', 'AND');
    }

    /**
     * @param $field
     * @param array $keys
     * @return $this
     */

    public function orIn($field, array $keys): KyutePDO
    {
        return $this->in($field, $keys, '', 'OR');
    }

    /**
     * @param $field
     * @param array $keys
     * @return $this
     */

    public function orNotIn($field, array $keys): KyutePDO
    {
        return $this->in($field, $keys, 'NOT ', 'OR');
    }

    /**
     * @param $field
     * @param $value1
     * @param $value2
     * @param string $type
     * @param string $andOr
     * @return $this
     */

    public function between($field, $value1, $value2, $type = '', $andOr = 'AND'): KyutePDO
    {
        $where = '(' . $field . ' ' . $type . 'BETWEEN ' . ($this->escape($value1) . ' AND ' . $this->escape($value2)) . ')';
        if ($this->grouped) {
            $where = '(' . $where;
            $this->grouped = false;
        }

        $this->where = is_null($this->where)
            ? $where
            : $this->where . ' ' . $andOr . ' ' . $where;

        return $this;
    }

    /**
     * @param $field
     * @param $value1
     * @param $value2
     * @return $this
     */

    public function notBetween($field, $value1, $value2): KyutePDO
    {
        return $this->between($field, $value1, $value2, 'NOT ', 'AND');
    }

    /**
     * @param $field
     * @param $value1
     * @param $value2
     * @return $this
     */

    public function orBetween($field, $value1, $value2): KyutePDO
    {
        return $this->between($field, $value1, $value2, '', 'OR');
    }

    /**
     * @param $field
     * @param $value1
     * @param $value2
     * @return $this
     */

    public function orNotBetween($field, $value1, $value2): KyutePDO
    {
        return $this->between($field, $value1, $value2, 'NOT ', 'OR');
    }

    /**
     * @param $field
     * @param $data
     * @param string $type
     * @param string $andOr
     * @return $this
     */

    public function like($field, $data, $type = '', $andOr = 'AND'): KyutePDO
    {
        $like = $this->escape($data);
        $where = $field . ' ' . $type . 'LIKE ' . $like;

        if ($this->grouped) {
            $where = '(' . $where;
            $this->grouped = false;
        }

        $this->where = is_null($this->where)
            ? $where
            : $this->where . ' ' . $andOr . ' ' . $where;

        return $this;
    }

    /**
     * @param $field
     * @param $data
     * @return $this
     */

    public function orLike($field, $data): KyutePDO
    {
        return $this->like($field, $data, '', 'OR');
    }

    /**
     * @param $field
     * @param $data
     * @return $this
     */

    public function notLike($field, $data): KyutePDO
    {
        return $this->like($field, $data, 'NOT ', 'AND');
    }

    /**
     * @param $field
     * @param $data
     * @return $this
     */

    public function orNotLike($field, $data): KyutePDO
    {
        return $this->like($field, $data, 'NOT ', 'OR');
    }

    /**
     * @param $limit
     * @param null $limitEnd
     * @return $this
     */

    public function limit($limit, $limitEnd = null): KyutePDO
    {
        $this->limit = !is_null($limitEnd)
            ? $limit . ', ' . $limitEnd
            : $limit;

        return $this;
    }

    /**
     * @param $offset
     * @return $this
     */

    public function offset($offset): KyutePDO
    {
        $this->offset = $offset;

        return $this;
    }

    /**
     * @param $perPage
     * @param $page
     * @return $this
     */

    public function pagination($perPage, $page): KyutePDO
    {
        $this->limit = $perPage;
        $this->offset = (($page > 0 ? $page : 1) - 1) * $perPage;

        return $this;
    }

    /**
     * @param $orderBy
     * @param null $orderDir
     * @return $this
     */

    public function orderBy($orderBy, $orderDir = null): KyutePDO
    {
        if (!is_null($orderDir)) {
            $this->orderBy = $orderBy . ' ' . strtoupper($orderDir);
        } else {
            $this->orderBy = stristr($orderBy, ' ') || strtolower($orderBy) === 'rand()'
                ? $orderBy
                : $orderBy . ' ASC';
        }

        return $this;
    }

    /**
     * @param $groupBy
     * @return $this
     */

    public function groupBy($groupBy): KyutePDO
    {
        $this->groupBy = is_array($groupBy) ? implode(', ', $groupBy) : $groupBy;

        return $this;
    }

    /**
     * @param $field
     * @param null $operator
     * @param null $val
     * @return $this
     */

    public function having($field, $operator = null, $val = null): KyutePDO
    {
        if (is_array($operator)) {
            $fields = explode('?', $field);
            $where = '';
            foreach ($fields as $key => $value) {
                if (!empty($value)) {
                    $where .= $value . (isset($operator[$key]) ? $this->escape($operator[$key]) : '');
                }
            }
            $this->having = $where;
        } elseif (!in_array($operator, $this->operators)) {
            $this->having = $field . ' > ' . $this->escape($operator);
        } else {
            $this->having = $field . ' ' . $operator . ' ' . $this->escape($val);
        }

        return $this;
    }

    /**
     * @return int
     */

    public function numRows(): int
    {
        return $this->numRows;
    }

    /**
     * @return null
     */

    public function insertId()
    {
        return $this->insertId;
    }

    /**
     *
     */

    public function error()
    {
        if ($this->debug === true) {
            if (php_sapi_name() === 'cli') {
                die("Query: " . $this->query . PHP_EOL . "Error: " . $this->error . PHP_EOL);
            }

            $msg = '<h1>Database Error</h1>';
            $msg .= '<h4>Query: <em style="font-weight:normal;">"' . $this->query . '"</em></h4>';
            $msg .= '<h4>Error: <em style="font-weight:normal;">' . $this->error . '</em></h4>';
            die($msg);
        }

        throw new PDOException($this->error . '. (' . $this->query . ')');
    }

    /**
     * @param null $type
     * @param null $argument
     * @return $this|array|false|int|mixed|string
     */

    public function get($type = null, $argument = null)
    {
        $this->limit = 1;
        $query = $this->getAll(true);
        return $type === true ? $query : $this->query($query, false, $type, $argument);
    }

    /**
     * @param null $type
     * @param null $argument
     * @return $this|array|false|int|mixed|string
     */

    public function getAll($type = null, $argument = null)
    {
        $query = 'SELECT ' . $this->select . ' FROM ' . $this->from;

        if (!is_null($this->join)) {
            $query .= $this->join;
        }

        if (!is_null($this->where)) {
            $query .= ' WHERE ' . $this->where;
        }

        if (!is_null($this->groupBy)) {
            $query .= ' GROUP BY ' . $this->groupBy;
        }

        if (!is_null($this->having)) {
            $query .= ' HAVING ' . $this->having;
        }

        if (!is_null($this->orderBy)) {
            $query .= ' ORDER BY ' . $this->orderBy;
        }

        if (!is_null($this->limit)) {
            $query .= ' LIMIT ' . $this->limit;
        }

        if (!is_null($this->offset)) {
            $query .= ' OFFSET ' . $this->offset;
        }

        return $type === true ? $query : $this->query($query, true, $type, $argument);
    }

    /**
     * @param array $data
     * @param false $type
     * @return false|string|null
     */

    public function insert(array $data, $type = false)
    {
        $query = 'INSERT INTO ' . $this->from;

        $values = array_values($data);
        if (isset($values[0]) && is_array($values[0])) {
            $column = implode(', ', array_keys($values[0]));
            $query .= ' (' . $column . ') VALUES ';
            foreach ($values as $value) {
                $val = implode(', ', array_map([$this, 'escape'], $value));
                $query .= '(' . $val . '), ';
            }
            $query = trim($query, ', ');
        } else {
            $column = implode(', ', array_keys($data));
            $val = implode(', ', array_map([$this, 'escape'], $data));
            $query .= ' (' . $column . ') VALUES (' . $val . ')';
        }

        if ($type === true) {
            return $query;
        }

        if ($this->query($query, false)) {
            $this->insertId = $this->pdo->lastInsertId();
            return $this->insertId();
        }

        return false;
    }

    /**
     * @param array $data
     * @param false $type
     * @return $this|array|false|int|mixed|string
     */

    public function update(array $data, $type = false)
    {
        $query = 'UPDATE ' . $this->from . ' SET ';
        $values = [];

        foreach ($data as $column => $val) {
            $values[] = $column . '=' . $this->escape($val);
        }
        $query .= implode(',', $values);

        if (!is_null($this->where)) {
            $query .= ' WHERE ' . $this->where;
        }

        if (!is_null($this->orderBy)) {
            $query .= ' ORDER BY ' . $this->orderBy;
        }

        if (!is_null($this->limit)) {
            $query .= ' LIMIT ' . $this->limit;
        }

        return $type === true ? $query : $this->query($query, false);
    }

    /**
     * @param false $type
     * @return $this|array|false|int|mixed|string
     */

    public function delete($type = false)
    {
        $query = 'DELETE FROM ' . $this->from;

        if (!is_null($this->where)) {
            $query .= ' WHERE ' . $this->where;
        }

        if (!is_null($this->orderBy)) {
            $query .= ' ORDER BY ' . $this->orderBy;
        }

        if (!is_null($this->limit)) {
            $query .= ' LIMIT ' . $this->limit;
        }

        if ($query === 'DELETE FROM ' . $this->from) {
            $query = 'TRUNCATE TABLE ' . $this->from;
        }

        return $type === true ? $query : $this->query($query, false);
    }

    /**
     * @return $this|array|false|int|mixed|string
     */

    public function analyze()
    {
        return $this->query('ANALYZE TABLE ' . $this->from, false);
    }

    /**
     * @return $this|array|false|int|mixed|string
     */

    public function check()
    {
        return $this->query('CHECK TABLE ' . $this->from, false);
    }

    /**
     * @return $this|array|false|int|mixed|string
     */

    public function checksum()
    {
        return $this->query('CHECKSUM TABLE ' . $this->from, false);
    }

    /**
     * @return $this|array|false|int|mixed|string
     */

    public function optimize()
    {
        return $this->query('OPTIMIZE TABLE ' . $this->from, false);
    }

    /**
     * @return $this|array|false|int|mixed|string
     */

    public function repair()
    {
        return $this->query('REPAIR TABLE ' . $this->from, false);
    }

    /**
     * @return bool
     */

    public function transaction(): bool
    {
        if (!$this->transactionCount++) {
            return $this->pdo->beginTransaction();
        }

        $this->pdo->exec('SAVEPOINT trans' . $this->transactionCount);
        return $this->transactionCount >= 0;
    }

    /**
     * @return bool
     */

    public function commit(): bool
    {
        if (!--$this->transactionCount) {
            return $this->pdo->commit();
        }

        return $this->transactionCount >= 0;
    }

    /**
     * @return bool
     */

    public function rollBack(): bool
    {
        if (--$this->transactionCount) {
            $this->pdo->exec('ROLLBACK TO trans' . ($this->transactionCount + 1));
            return true;
        }

        return $this->pdo->rollBack();
    }

    /**
     * @return false|int|mixed|null
     */

    public function exec()
    {
        if (is_null($this->query)) {
            return null;
        }

        $query = $this->pdo->exec($this->query);
        if ($query === false) {
            $this->error = $this->pdo->errorInfo()[2];
            $this->error();
        }

        return $query;
    }

    /**
     * @param null $type
     * @param null $argument
     * @param false $all
     * @return array|mixed|null
     */

    public function fetch($type = null, $argument = null, $all = false)
    {
        if (is_null($this->query)) {
            return null;
        }

        $query = $this->pdo->query($this->query);
        if (!$query) {
            $this->error = $this->pdo->errorInfo()[2];
            $this->error();
        }

        $type = $this->getFetchType($type);
        if ($type === PDO::FETCH_CLASS) {
            $query->setFetchMode($type, $argument);
        } else {
            $query->setFetchMode($type);
        }

        $result = $all ? $query->fetchAll() : $query->fetch();
        $this->numRows = is_array($result) ? count($result) : 1;
        return $result;
    }

    /**
     * @param null $type
     * @param null $argument
     * @return array|mixed|null
     */

    public function fetchAll($type = null, $argument = null)
    {
        return $this->fetch($type, $argument, true);
    }

    /**
     * @param $query
     * @param bool $all
     * @param null $type
     * @param null $argument
     * @return $this|array|false|int|mixed|string
     */

    public function query($query, $all = true, $type = null, $argument = null)
    {
        $this->reset();

        if (is_array($all) || func_num_args() === 1) {
            $params = explode('?', $query);
            $newQuery = '';
            foreach ($params as $key => $value) {
                if (!empty($value)) {
                    $newQuery .= $value . (isset($all[$key]) ? $this->escape($all[$key]) : '');
                }
            }
            $this->query = $newQuery;
            return $this;
        }

        $this->query = preg_replace('/\s\s+|\t\t+/', ' ', trim($query));
        $str = false;
        foreach (['select', 'optimize', 'check', 'repair', 'checksum', 'analyze'] as $value) {
            if (stripos($this->query, $value) === 0) {
                $str = true;
                break;
            }
        }

        $type = $this->getFetchType($type);
        $cache = false;
        if (!is_null($this->cache) && $type !== PDO::FETCH_CLASS) {
            $cache = $this->cache->getCache($this->query, $type === PDO::FETCH_ASSOC);
        }

        if (!$cache && $str) {
            $sql = $this->pdo->query($this->query);
            if ($sql) {
                $this->numRows = $sql->rowCount();
                if ($this->numRows > 0) {
                    if ($type === PDO::FETCH_CLASS) {
                        $sql->setFetchMode($type, $argument);
                    } else {
                        $sql->setFetchMode($type);
                    }
                    $this->result = $all ? $sql->fetchAll() : $sql->fetch();
                }

                if (!is_null($this->cache) && $type !== PDO::FETCH_CLASS) {
                    $this->cache->setCache($this->query, $this->result);
                }
                $this->cache = null;
            } else {
                $this->cache = null;
                $this->error = $this->pdo->errorInfo()[2];
                $this->error();
            }
        } elseif ((!$cache && !$str) || ($cache && !$str)) {
            $this->cache = null;
            $this->result = $this->pdo->exec($this->query);

            if ($this->result === false) {
                $this->error = $this->pdo->errorInfo()[2];
                $this->error();
            }
        } else {
            $this->cache = null;
            $this->result = $cache;
            $this->numRows = is_array($this->result) ? count($this->result) : ($this->result === '' ? 0 : 1);
        }

        $this->queryCount++;
        return $this->result;
    }

    /**
     * @param $data
     * @return false|float|int|string
     */

    public function escape($data)
    {
        return $data === null ? 'NULL' : (is_int($data) || is_float($data) ? $data : $this->pdo->quote($data)
        );
    }

    /**
     * @param $time
     * @return $this
     */

    public function cache($time): KyutePDO
    {
        $this->cache = new Cache($this->cacheDir, $time);

        return $this;
    }

    /**
     * @return int
     */

    public function queryCount(): int
    {
        return $this->queryCount;
    }

    /**
     * @return null
     */

    public function getQuery()
    {
        return $this->query;
    }

    /**
     *
     */

    public function __destruct()
    {
        $this->pdo = null;
    }

    /**
     *
     */

    protected function reset()
    {
        $this->select   = '*';
        $this->from     = null;
        $this->where    = null;
        $this->limit    = null;
        $this->offset   = null;
        $this->orderBy  = null;
        $this->groupBy  = null;
        $this->having   = null;
        $this->join     = null;
        $this->grouped  = false;
        $this->numRows  = 0;
        $this->insertId = null;
        $this->query    = null;
        $this->error    = null;
        $this->result   = [];
        $this->transactionCount = 0;
    }

    /**
     * @param $type
     * @return int
     */

    protected function getFetchType($type): int
    {
        return $type === 'class' ? PDO::FETCH_CLASS : ($type === 'array' ? PDO::FETCH_ASSOC : PDO::FETCH_OBJ);
    }

    /**
     * @param $fields
     */

    private function optimizeSelect($fields)
    {
        $this->select = $this->select === '*' ? $fields : $this->select . ', ' . $fields;
    }
}

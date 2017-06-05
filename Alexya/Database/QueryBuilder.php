<?php
namespace Alexya\Database;

/**
 * Query builder class.
 *
 * Provides helper methods for building queries in an easy way.
 * Example:
 *
 * ```php
 * $query = new QueryBuilder()
 *              ->select()
 *              ->from("users")
 *              ->where(
 *                  "AND" => [
 *                      ["username" => "test"],
 *                      ["password" => "test"]
 *                  ]
 *              )
 *              ->limit(1);
 *
 * echo $query->getQuery(); //SELECT * FROM `users` WHERE `username`='test' AND `password`='test' LIMIT 1;
 * ```
 *
 * @author Manulaiko <manulaiko@gmail.com>
 */
class QueryBuilder
{
    /**
     * Generated query as array.
     *
     * @var array
     */
    private $_query = [];

    /**
     * Database connection.
     *
     * @var Connection
     */
    private $_connection;

    /**
     * Constructor.
     *
     * @param Connection $connection Database connection.
     */
    public function __construct(Connection $connection)
    {
        $this->_connection = $connection;
    }

    ////////////////////////////////////
    // Start query generation methods //
    ////////////////////////////////////
    /**
     * Begins the select query.
     *
     * @param string|array $columns Column(s) to select, if empty has same effect as "*".
     *
     * @return QueryBuilder Chainability object.
     */
    public function select($columns = "*") : QueryBuilder
    {
        $this->_query[] = "SELECT";

        //Parse column(s)
        if(is_array($columns)) {
            $select_columns = "";

            $size = sizeof($columns);
            for($i = 0; $i < $size; $i++) {
                if($i > 0) {
                    $select_columns .= ', ';
                }

                $select_columns .= '`'. $this->sanitize((string)$columns[$i]) .'`';
            }

            $this->_query[] = $select_columns;

            return $this;
        }

        $select_columns = "*";
        if(
            !empty($columns) &&
            $columns !== "*"
        ) {
            $select_columns = '`'. $this->sanitize((string)$columns) .'`';
        }

        $this->_query[] = $select_columns;

        return $this;
    }

    /**
     * Begins the insert query
     *
     * @param string $table Table name
     *
     * @return QueryBuilder Chainability object
     */
    public function insert(string $table) : QueryBuilder
    {
        $this->_query[] = "INSERT INTO";
        $this->_query[] = "`". $this->sanitize($table) ."`";

        return $this;
    }

    /**
     * Begins the update query
     *
     * @param string $table Table name
     *
     * @return QueryBuilder Chainability object
     */
    public function update(string $table) : QueryBuilder
    {
        $this->_query[] = "UPDATE";
        $this->_query[] = "`". $this->sanitize($table) ."`";

        return $this;
    }

    /**
     * Begins the delete query
     *
     * @param string $table Table name
     *
     * @return QueryBuilder Chainability object
     */
    public function delete(string $table) : QueryBuilder
    {
        $this->_query[] = "DELETE FROM";
        $this->_query[] = "`". $this->sanitize($table) ."`";

        return $this;
    }

    /**
     * Begins the FORM part of the query
     *
     * @param string $tables Table name
     *
     * @return QueryBuilder Chainability object
     */
    public function from(string $tables) : QueryBuilder
    {
        $this->_query[] = "FROM";
        $this->_query[] = '`'. $this->sanitize($tables) .'`';

        return $this;
    }

    /**
     * Begins the WHERE statement of the query
     *
     * Example:
     *
     * ```php
     * $query->where([
     *     "username" => "test"
     * ]);
     * $query->where(
     *     "AND" => [
     *         ["username" => "test"],
     *         ["password" => "test"]
     *     ]
     * );
     * ```
     *
     * @param array $condition Where condition
     *
     * @return QueryBuilder Chainability object
     */
    public function where(array $condition) : QueryBuilder
    {
        $this->_query[] = "WHERE";

        foreach($condition as $key => $value) {
            $this->_query[] = $this->parseTags([$key, $value]);
        }

        return $this;
    }

    /**
     * Begins the ORDER BY part of the query
     *
     * @param string $column Column name
     * @param string $method Order method
     *
     * @return QueryBuilder Chainability object
     */
    public function order(string $column, string $method = "DESC") : QueryBuilder
    {
        $this->_query[] = "ORDER BY";
        $this->_query[] = "`". $this->sanitize($column) ."`";
        $this->_query[] = (strtoupper($method) === "DESC") ? "DESC" : "ASC";

        return $this;
    }

    /**
     * Begins the LIMIT part of the query
     *
     * @param int|array $limit Limit value
     *
     * @return QueryBuilder Chainability object
     */
    public function limit($limit) : QueryBuilder
    {
        $this->_query[] = "LIMIT";

        if(is_array($limit)) {
            $this->_query[] = $this->getQuoted($limit[0]).",";
            $this->_query[] = $this->getQuoted($limit[1]);

            return $this;
        }

        $this->_query[] = $this->getQuoted($limit);

        return $this;
    }

    /**
     * Begins the OFFSET part of the query
     *
     * @param int $offset Offset value
     *
     * @return QueryBuilder Chainability object
     */
    public function offset(int $offset) : QueryBuilder
    {
        $this->_query[] = "OFFSET";
        $this->_query[] = $offset;

        return $this;
    }

    /**
     * Begins the SET part of the query
     *
     * @param array $values Values to set
     *
     * @return QueryBuilder Chainability object
     */
    public function set(array $values) : QueryBuilder
    {
        $this->_query[] = "SET";

        $set = [];
        foreach($values as $key => $val) {
            $set[] = $this->parseTags([$key, $val]);
        }

        $this->_query[] = implode(", ", $set);

        return $this;
    }

    /**
     * Begins the VALUES part of the query
     *
     * @param array $values Values to insert
     *
     * @return QueryBuilder Chainability object
     */
    public function values(array $values) : QueryBuilder
    {
        $args    = func_get_args();
        $columns = [];
        $_values = [];

        foreach($values as $key => $value) {
            preg_match("/\(JSON\)\s*([\w]+)/i", $key, $jsonTag);

            if(
                is_array($value) ||
                is_object($value)
            ) {
                if(isset($jsonTag[0])) {
                    $key       = preg_replace('/\(JSON\)\s*([\w]+)/i', '$1', $key);
                    $_values[] = $this->getQuoted(json_encode($value));
                } else {
                    $_values[] = $this->getQuoted(serialize($value));
                }
            } else if(is_bool($value)) {
                $_values[] = ($value ? 1 : 0);
            } else if(is_null($value)) {
                $_values[] = "NULL";
            } else {
                $_values[] = $this->getQuoted($value);
            }

            $columns[] = "`". $this->sanitize($key) ."`";
        }

        $this->_query[] = "(". implode(", ", $columns) .")";
        $this->_query[] = "VALUES";
        $this->_query[] = "(". implode(", ", $_values) .")";

        return $this;
    }

    /**
     * Adds raw SQL to the query
     *
     * @param mixed $sql SQL to add to query
     *
     * @return QueryBuilder Chainability object
     */
    public function sql($sql) : QueryBuilder
    {
        $this->_query[] = $sql;

        return $this;
    }
    //////////////////////////////////
    // End query generation methods //
    //////////////////////////////////

    /**
     * Parses and returns the query
     *
     * @return string SQL Query
     */
    public function getQuery() : string
    {
        $query = implode(" ", $this->_query).";";

        return $query;
    }

    /**
     * To string method
     *
     * @return string SQL Query
     */
    public function __toString() : string
    {
        return $this->getQuery();
    }

    /**
     * Parses tags
     *
     * @param array $source idk proper description
     *
     * @return string Source without tags
     */
    public function parseTags(array $source) : string
    {
        $column = $source[0];
        $value  = $source[1];
        $clause = "";

        preg_match("/(#?)([\w\.\-]+)(\[(\>|\>\=|\<|\<\=|\!|\<\>|\>\<|\!?~)\])?/i", $column, $tag);
        if(isset($tag[4])) {
            $column   = "`". $this->sanitize($tag[2]) ."`";
            $operator = $tag[4];

            if($operator === "!") {
                if(is_array($value)) {
                    $str = [];
                    foreach($value as $val) {
                        $str[] = $this->getQuoted($val);
                    }

                    $clause .= $column ." NOT IN (". implode(", ", $str) .")";
                } else if(is_null($value)) {
                    $clause .= $column ." IS NOT NULL ";
                } else if(is_bool($value)) {
                    $clause .= $column ."!=". ($value ? '1' : '0');
                } else {
                    $clause .= $column ."!=". $this->getQuoted($value);
                }
            } else if(
                $operator === "<>" ||
                $operator === "><"
            ) {
                if(gettype($value) == "array") {
                    $c = "";
                    if($operator == "><") {
                        $c .= "NOT ";
                    }

                    $clause = '('. $column .' '. $c .' BETWEEN '. $this->getQuoted($value[0]) .' AND '. $this->getQuoted($value[1]) .')';
                }
            } else if(
                $operator === '~' ||
                $operator === '!~'
            ) {
                if(!is_array($value)) {
                    $value = array($value);
                }
                $like_clauses = array();

                foreach($value as $item) {
                    $item   = strval($item);
                    $suffix = mb_substr($item, -1, 1);

                    if($suffix === '_') {
                        $item = substr_replace($item, '%', -1);
                    } else if ($suffix === '%') {
                        $item = '%'. substr_replace($item, '', -1, 1);
                    } else if (preg_match('/^(?!%).+(?<!%)$/', $item)) {
                        $item = '%'. $item .'%';
                    }

                    $like_clauses[] = $column . ($operator === '!~' ? ' NOT' : '') .' LIKE '. $this->getQuoted($item);
                }

                $clause = implode(' OR ', $like_clauses);
            } else if(in_array($operator, array('>', '>=', '<', '<='))) {
                if(is_numeric($value)) {
                    $clause = $column .' '. $operator .' '. $value;
                } else if(strpos($key, '#') === 0) {
                    $clause = $column .' '. $operator .' '. $this->getQuoted($value);
                } else {
                    $clause = $column .' '. $operator .' '. $this->getQuoted($value);
                }
            }
        } else if($column === "AND") {
            if(!is_array($value)) {
                return "";
            }

            $cond = [];
            foreach($value as $key => $v) {
                $cond[] = $this->parseTags([$key, $v]);
            }

            $clause = implode(" AND ", $cond);
        } else if($column === "OR") {
            if(!is_array($value)) {
                return "";
            }

            $cond = [];
            foreach($value as $key => $v) {
                $cond[] = $this->parseTags([$key, $v]);
            }

            $clause = implode(" OR ", $cond);
        } else {
            $column = "`". $this->sanitize((string)$column) ."`";

            if(is_null($value)) {
                $clause = $column ." IS NULL";
            } else if(is_array($value)) {
                $values = [];
                foreach($value as $val) {
                    $values[] = $this->getQuoted($val);
                }

                $clause = $column .' IN ('. implode(", ", $values) .')';
            } else if(is_bool($value)) {
                $clause = $column .'='. ($value ? '1' : '0');
            } else {
                $clause = $column . '=' . $this->getQuoted($value);
            }
        }

        return $clause;
    }

    /**
     * Returns $str with quotes
     *
     * @param mixed $str String to quote
     *
     * @return string String with quotes
     */
    public function getQuoted($str) : string
    {
        if(
            is_array($str) ||
            is_object($str)
        ) {
            return "'". $this->sanitize(json_encode($str)) ."'";
        } else if(
            is_int($str) ||
            is_float($str)
        ) {
            return (string)$str;
        }

        return "'". $this->sanitize((string)$str) ."'";
    }

    /**
     * Resets query to beginning
     */
    public function clear()
    {
        $this->_query = [];
    }

    /**
     * Sanitizes the input.
     *
     * @param string $input Input to sanitize.
     *
     * @return string SQL injection free `$input`.
     */
    public function sanitize(string $input) : string
    {
        $input = $this->_connection->getConnection()->quote($input);

        return substr($input, 1, -1);
    }

    /**
     * Executes the query.
     *
     * @return array The result of the query.
     */
    public function execute() : array
    {
        return $this->_connection->execute($this->getQuery());
    }
}

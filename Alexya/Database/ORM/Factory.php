<?php
namespace Alexya\Database\ORM;

use Alexya\Database\Connection;
use Alexya\Database\QueryBuilder;
use Alexya\Tools\Str;
use Exception;

/**
 * Factory class.
 * ==============
 *
 * The ORM factory will execute the queries to the database server
 * and will perform the necessary operations in order to map the
 * query result to a PHP object.
 *
 * It has the following methods:
 *
 *  * `find`: Finds and returns a single value from the table.
 *  * `where`: Finds and returns multiple values from the table.
 *  * `by`: Returns one or multiple values with specified column ordering.
 *
 * But before you start using the ORM factory you need to configure it.
 * The configuration is done by calling the `initialize` method which accepts
 * as parameter the `Connection` object, a callback to parse class name to
 * table name and a callback to parse class name to primary key name.
 * Second and third parameters are optional will default to `table` and `primaryKey`.
 *
 * Example:
 *
 * ```php
 * Factory::initialize($connection);
 *
 * $users = Factory::all("users"); // SELECT * FROM `users`;
 * $user = Factory::find([
 *     "username" => "test",
 *     "password" => "test",
 * ], "users"); // SELECT * FROM `users` WHERE `username`='test' AND `password`='test';
 * ```
 *
 * As you can see, the last parameter for the methods to query the database is
 * the table name. If you extend this class with the name of the table, you can omit it:
 *
 * ```php
 * Factory::initialize($connection);
 *
 * class Users extends Factory
 * {
 *
 * }
 *
 * $users = Users::all(); // SELECT * FROM `users`;
 * $user = Factory::find([
 *     "username" => "test",
 *     "password" => "test",
 * ]); // SELECT * FROM `users` WHERE `username`='test' AND `password`='test';
 * ```
 *
 * By extending the class you can also control how the ORM models are built or override the
 * default table and primary key parsers for a specific table:
 *
 * ```php
 * Factory::initialize($connection);
 *
 * class Users extends Factory
 * {
 *     public static table()
 *     {
 *         return "theUsers";
 *     }
 *
 *     public static primaryKey()
 *     {
 *         return "userID";
 *     }
 * }
 *
 * $users = Users::all(); // SELECT * FROM `theUsers`;
 * $user = Factory::find(1); // SELECT * FROM `theUsers` WHERE `userID`=1;
 * ```
 *
 * @author Manulaiko <manulaiko@gmail.com>
 */
class Factory
{
    /**
     * Connection object.
     *
     * @var Connection
     */
    protected static $_connection;

    /**
     * Callback to parse a class name to a table name.
     *
     * @var callable
     */
    protected static $_tableParser = null;

    /**
     * Callback to parse a class name to a primary key name.
     *
     * @var callable
     */
    protected static $_primaryKeyParser = null;

    /**
     * Initializes the factory.
     *
     * @param Connection $connection       Connection object.
     * @param callable   $tableParser      Callback to parse a class name to a primary key name.
     * @param callable   $primaryKeyParser Callback to parse a class name to a primary key name.
     *
     * @throws Exception If the factory has already been initialized.
     */
    public static function initialize(Connection $connection, callable $tableParser = null, callable $primaryKeyParser = null) : void
    {
        if(static::$_connection != null) {
            throw new Exception("Factory class has already been initialized!");
        }

        static::$_connection = $connection;

        if($tableParser != null) {
            static::$_tableParser = $tableParser;
        }

        if($primaryKeyParser != null) {
            static::$_primaryKeyParser = $primaryKeyParser;
        }
    }

    /**
     * Parses a class name to a table name.
     *
     * The table name is the `snake_case`, plural name of the class.
     * Also, it assumes that the class is located in the `\Application\ORM` namespace.
     *
     * @param string $class   Class name or table name to parse.
     * @param bool   $toTable Whether the output should be the table name or the class name.
     *
     * @return string Table name for `$class`.
     */
    public static function table(string $class, bool $toTable = true) : string
    {
        if($toTable) {
            if(Str::startsWith($class, "Application\\ORM\\")) {
                $class = substr($class, 16);
            }

            $class = explode("\\", $class);
            $table = Str::snake(Str::plural($class));

            return $table;
        }

        $class = explode("_", $class);
        $class = implode("\\", Str::singular($class));

        return "\\Application\\ORM\\{$class}";
    }

    /**
     * Parses a class name to a primary key name.
     *
     * By default the primary key is always `id`.
     *
     * @param string $class Class to parse.
     *
     * @return string Primary key for `$class`.
     */
    public static function primaryKey(string $class) : string
    {
        return "id";
    }

    /////////////////////////
    // Start query methods //
    /////////////////////////

    /**
     * Finds and returns a single value from the database.
     *
     * The `$criteria` parameter can be an integer (the value of the primary key)
     * or an array (the `WHERE` clause of the query).
     *
     * The `$columns` parameter can be an array (the columns to select) or
     * a string (the table to query).
     *
     * The `$table` parameter is the table to query, if `$columns` is a string
     * and `$table` isn't empty, it will assume that `$columns` is `[$columns]`.
     *
     * Example:
     *
     * ```php
     * $user = Factory::find(1, "users"); // SELECT * FROM `users` WHERE `id`=1;
     * $user = Factory::find([
     *     "username" => "test"
     * ], "username", "users"); // SELECT `username` FROM `users` WHERE `username`='test';
     * ```
     *
     * @param int|array    $criteria Primary key or `WHERE` clause of the query.
     * @param string|array $columns  Columns to select (or table to query).
     * @param string       $table    Table to query.
     *
     * @return Model ORM Model with the result of the query.
     */
    public static function find($criteria, $columns = [], $table = "") : ?Model
    {
        if(
            is_string($columns) &&
            empty($table)
        ) {
            $table = $columns;
        }

        if(
            is_string($columns) &&
            !empty($table)
        ) {
            $columns = [$columns];
        }

        if(empty($table)) {
            $table = self::_callTableParser(get_called_class());
        }
        if(empty($columns)) {
            $columns = "*";
        }

        $where = [
            self::_callPrimaryKeyParser(get_called_class()) => $criteria
        ];

        if(is_array($criteria)) {
            $where = $criteria;
        }

        $query = new QueryBuilder(static::$_connection);
        $query->select($columns)
              ->from($table)
              ->where($where)
              ->limit(1);

        return static::build($query->execute(), $table);
    }

    /**
     * Returns all values from the database.
     *
     * The `$columns` parameter can be an array (the columns to select),
     * a string (the table to query) or an integer (the `LIMIT` clause).
     *
     * The `$limit` parameter is the `LIMIT` clause, if -1, all records will be returned.
     * If it's a string, it will assume its `$table`.
     *
     * The `$table` parameter is the table to query.
     *
     * Example:
     *
     * ```php
     * $users = Factory::where([
     *     "has_verified_email" => false
     * ], "users"); // SELECT * FROM `users` WHERE `has_verified_email`=0;
     * ```
     *
     * @param array            $criteria `WHERE` clause.
     * @param string|array     $columns  Columns to select (or `LIMIT` clause, or table to query).
     * @param int|array|string $limit    `LIMIT` clause (or table name).
     * @param string           $table    Table to query.
     *
     * @return Model[] Results from the database.
     */
    public static function where(array $criteria, $columns = [], $limit = -1, string $table = "") : array
    {
        if(is_int($columns)) {
            $limit   = $columns;
            $columns = [];
        }

        if(
            is_string($limit) &&
            $table == ""
        ) {
            $table = $limit;
            $limit = -1;
        }

        if(
            is_string($columns) &&
            empty($table)
        ) {
            $table   = $columns;
            $columns = [];
        }

        if(
            is_string($columns) &&
            !empty($table)
        ) {
            $columns = [$columns];
        }

        if(empty($table)) {
            $table = self::_callTableParser(get_called_class());
        }
        if(empty($columns)) {
            $columns = "*";
        }
        if(empty($limit)) {
            $limit = -1;
        }

        $query = new QueryBuilder(static::$_connection);
        $query->select($columns)
              ->from($table)
              ->where($criteria);

        if($limit > -1) {
            $query->limit($limit);
        }

        $result = $query->execute();

        if(empty($result)) {
            return [];
        }

        $ret = [];
        foreach($result as $row) {
            $ret[] = static::build($row, $table);
        }

        return $ret;
    }

    /**
     * Returns one or multiple values from the database.
     *
     * The `$column` parameter is the column to order the results.
     *
     * The `$columns` parameter can be an array (the columns to select),
     * a string (the table to query), a boolean (the order type) or
     * an integer (the `LIMIT` clause).
     *
     * The `$order` parameter can be a boolean (the order type),
     * an integer (the `LIMIT` clause) or a string (table to query).
     *
     * The `$limit` parameter is the `LIMIT` clause, if -1, all records will be returned.
     * If it's a string, it will assume its `$table`.
     *
     * The `$table` parameter is the table to query.
     *
     * Example:
     *
     * ```php
     * $users = Factory::by("lastLogin", false, 10, "users"); // SELECT * FROM `users` ORDER BY `lastLogin` DESC LIMIT 10;
     * $users = Factory::by("lastLogin", true, "users"); // SELECT * FROM `users` ORDER BY `lastLogin` ASC;
     * ```
     *
     * @param string                $column  The column to order results.
     * @param array|string|bool|int $columns Columns to select (or order type, or `LIMIT` clause, or table to query).
     * @param bool|int|string       $order   Order type (`true = ASC`, `false = DESC`) (or `LIMIT` clause, or table name).
     * @param int|array|string      $limit   The limit clause (or table name).
     * @param string                $table   Table to query.
     *
     * @return Model[] Results from the database.
     */
    public static function by(string $column, $columns = [], $order = false, $limit = -1, $table = "")
    {
        if(is_int($columns)) {
            $limit   = $columns;
            $columns = [];
        }

        if(is_bool($columns)) {
            $order   = $columns;
            $columns = [];
        }

        if(
            is_string($order) &&
            empty($table)
        ) {
            $table = $order;
            $order = false;
        }

        if(is_int($order)) {
            $limit = $order;
            $order = false;
        }

        if(
            is_string($limit) &&
            $table == ""
        ) {
            $table = $limit;
            $limit = -1;
        }

        if(
            is_string($columns) &&
            empty($table)
        ) {
            $table   = $columns;
            $columns = [];
        }

        if(
            is_string($columns) &&
            !empty($table)
        ) {
            $columns = [$columns];
        }

        if(empty($table)) {
            $table = self::_callTableParser(get_called_class());
        }
        if(empty($columns)) {
            $columns = "*";
        }
        if(empty($limit)) {
            $limit = -1;
        }
        if(empty($order)) {
            $order = false;
        }

        $query = new QueryBuilder(static::$_connection);
        $query->select($columns)
              ->from($table)
              ->order($column, ($order) ? "ASC" : "DESC");

        if($limit > -1) {
            $query->limit($limit);
        }

        $result = $query->execute();

        if(empty($result)) {
            return [];
        }

        $ret = [];
        foreach($result as $row) {
            $ret[] = static::build($row, $table);
        }

        return $ret;
    }

    ///////////////////////
    // End query methods //
    ///////////////////////

    /**
     * Builds and returns the `Model` instance.
     *
     * Parses the result of a query to build a model class.
     *
     * @param mixed  $query Query result.
     * @param string $table Table that was queried.
     *
     * @return Model ORM Model for `$query`.
     */
    public static function build($query, string $table) : ?Model
    {
        if(empty($query)) {
            return null;
        }

        $class = self::_callTableParser($table, false);

        if(!class_exists($class)) {
            $class = "\\Alexya\\Database\\ORM\\Model";
        }

        return new $class($query);
    }

    /**
     * Calls the table parser callback.
     *
     * @param string $class   Class name or table name to parse.
     * @param bool   $toTable Whether the output should be the table name or the class name.
     *
     * @return string Table name for `$class`.
     */
    private static function _callTableParser(string $class, bool $toTable = true) : string
    {
        if(static::$_tableParser == null) {
            return static::table($class, $toTable);
        }

        return (static::$_tableParser)($class, $toTable);
    }

    /**
     * Calls the primary key parser callback.
     *
     * @param string $class Class to parse.
     *
     * @return string Primary key name for `$class`.
     */
    private static function _callPrimaryKeyParser(string $class) : string
    {
        if(static::$_primaryKeyParser == null) {
            return static::primaryKey($class);
        }

        return (static::$_primaryKeyParser)($class);
    }
}

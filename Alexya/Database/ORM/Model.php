<?php
namespace Alexya\Database\ORM;

use Alexya\Database\{
    Connection,
    QueryBuilder
};

use Alexya\Tools\Str;

/**
 * Model class.
 *
 * This class acts as the mediator between the database table and the PHP code.
 *
 * Before anything you should initialize the class with the method `initialize`.
 * It accepts as parameter an object of type `\Alexya\Database\Connection` being the connection
 * to the database and a string being the base namespace where the Model classes are located, this
 * is if you want to store the Model classes in a separated namespace (default is "\"):
 *
 * ```php
 * $connection = new Connection("localhost", 3306, "root", "", "alexya");
 * Model::initialize($connection, "\Application\ORM");
 * ```
 *
 * You should write a class that extends this for each model, but when you're following
 * the naming conventions you'll surely finish with a package full of empty classes.
 * To prevent this you can use the method `instance` which accepts as parameter the name
 * of the database table.
 * Also, all static methods accepts as last parameter the name of the table that will be used.
 *
 * Extending this class allows you to take more control over it. You can specify
 * the name of the table, the name of the primary key, relations...
 *
 * The table name is, by default, the `snake_case`, plural name of the class, if you want to override it
 * change the property `_table` with the name of the table:
 *
 * ```php
 * class UsersTable extends Model
 * {
 *     protected $_table = "users"; // Without this, the table name would be `users_tables`, see \Alexya\Database\ORM\Model::getTable
 * }
 * ```
 *
 * The primary key is, by default, `id`, if you want to override it change the property `_primaryKey`
 * with the name of the primary key:
 *
 * ```php
 * class UsersTable extends Model
 * {
 *     protected $_primaryKey = "userID";
 * }
 * ```
 *
 * The method `onInstance` is executed when the class has been instantiated, use it instead of the constructor.
 *
 * The method `find` finds a record from the database and returns an instance of the Model class.
 * It accepts as parameter an integer being the value of the primary key or an array containing the
 * `WHERE` clause of the query:
 *
 * ```php
 * $user = UsersTable::find(1); // SELECT * FROM `users` WHERE `userID`=1 LIMIT 1
 * $user = UsersTable::find([
 *     "AND" => [
 *         "username" => "test",
 *         "password" => "test"
 *     ]
 * ]); // SELECT * FROM `users` WHERE `username`='test' AND `password`='test' LIMIT 1
 * ```
 *
 * You can send a second integer parameter being the amount of records to fetch from the database.
 * If it's omitted it will return a single record, otherwise an array of specified amount of records.
 *
 * To create a record use the method `create` which returns an instance of the Model class or instance a
 * new object directly:
 *
 * ```php
 * $newUser = UsersTable::create(); // same as `$newUser = new UsersTable();`
 *
 * $newUser->username = "foo";
 * $newUser->password = "bar";
 * ```
 *
 * To save the changes in the database use the method `save`.
 *
 * ```php
 * $newUser->save(); // INSERT INTO `users`(`username`, `password`) VALUES ('foo', 'bar')
 * ```
 *
 * @author Manulaiko <manulaiko@gmail.com>
 */
class Model
{
    /////////////////////////////////////////
    // Start Static methods and properties //
    /////////////////////////////////////////
    /**
     * Database connection.
     *
     * @var Connection
     */
    protected static $_connection;

    /**
     * Base namespace of the ORM classes.
     *
     * @var string
     */
    protected static $_baseNamespace = "\\";

    /**
     * Relations array.
     *
     * Each index of this array will be interpreted
     * as a relation rule.
     *
     * A rule can be:
     *
     *  * A string being the class name of the Model class that
     *    represents the table or the table name (if the class doesn't exist).
     *  * An array containing the configuration of the rule.
     *
     * The array can have the following index:
     *
     *  * `localKey`: Name of the local key used for the relation
     *                (defaults to the foreign table name followed by the local primary key).
     *  * `foreignKey`: Name of the foreign key used for the relation
     *                  (defaults to the foreign primary key).
     *  * `type`: Type of the relation (`has_one` or `has_many`)
     *            (defaults to `has_one`).
     *  * `name`: Name of the property to create for the resulting relation
     *            (defaults to the name of the class).
     *  * `amount`: Amount of records to retrieve for the relation
     *              (defaults to all records in the table).
     *  * `class`: Name of the class that will be instanced for the relation
     *             (defaults to the Model class of the foreign table in case
     *             of a `has_one` relation and an array of Model classes of
     *             the foreign table in case of a `has_many` relation).
     *  * `setRelations`: Whether the instanced models of the relation should process
     *                    their relations array too or not
     *                    (defaults to `false`).
     *
     * Example:
     *
     * ```php
     * class User extends Model
     * {
     *     protected static $_relations = [
     *         "Account" => [
     *             "localKey"     => "accounts_id", // Default value
     *             "foreignKey"   => "id", // Default value
     *             "name"         => "Account", // Default value
     *             "type"         => "has_one", // Default value
     *             "amount"       => 1, // Unnecessary
     *             "class"        => "Account", // Default value
     *             "setRelations" => false // Default value
     *         ]
     *     ];
     * }
     *
     * class User extends Model
     * {
     *     protected static $_relations = [
     *         "Account"
     *     ];
     * }
     *
     * class User extends Model
     * {
     *     protected static $_relations = [
     *         "Message" => [
     *             "localKey"   => "id",
     *             "foreignKey" => "to_users_id",
     *             "type"       => "has_many",
     *             "condition"  => "User::messageIsNotDeleted"
     *         ]
     *     ];
     *
     *     public static function messageIsNotDeleted(array $columns) : bool
     *     {
     *         return (!$columns["to_isDeleted"]);
     *     }
     * }
     * ```
     *
     * @var array
     */
    protected static $_relations = [];

    /**
     * Initializes the class.
     *
     * @param Connection $connection    Database connection.
     * @param string     $baseNamespace Base namespace of the ORM classes.
     */
    public static function initialize(Connection $connection, string $baseNamespace = "\\")
    {
        self::$_connection    = $connection;
        self::$_baseNamespace = Str::trailing($baseNamespace, "\\");
    }

    /**
     * Returns a new instance of the class.
     *
     * @param string $table Table name
     *
     * @return Model Instance of the class.
     */
    public static function instance(string $table) : Model
    {
        $model = new static([]);
        $model->_table = $table;

        return $model;
    }

    /**
     * Creates a new record.
     *
     * @param string $table Model table.
     *
     * @return Model Record instance.
     */
    public static function create(string $table = "") : Model
    {
        $model = new static();
        $model->_table = $table;

        return $model;
    }

    /**
     * Finds and returns one or more record from the database.
     *
     * @param int|string|array $id           Primary key value or `WHERE` clause.
     * @param int|array        $limit        Amount of records to retrieve from database,
     *                                       if `-1` (or empty array) an instance of the Model
     *                                       class will be returned.
     * @param string           $table        Table that will be used to get the records from.
     * @param bool             $setRelations Whether relations should be processed or not.
     *
     * @return Model|Model[] Records from the database.
     */
    public static function find($id, $limit = -1, string $table = "", bool $setRelations = true)
    {
        $query = new QueryBuilder(self::$_connection);
        $model = new static();

        $query->select("*");

        if($table === "") {
            $query->from($model->getTable());
        } else {
            $query->from($table);
        }

        $query = static::_setWhere($id, $query, $model->_primaryKey);

        if(
            is_int($limit) &&
            $limit < 0
        ) {
            $query->limit(1);
        } else {
            $query->limit($limit);
        }

        $result = $query->execute();
        $return = [];

        foreach($result as $r) {
            if(
                empty($limit) ||
                is_int($limit) && $limit < 0
            ) {
                $model = new static($r, $setRelations);
                if($table !== "") {
                    $model->_table = $table;
                }

                return $model;
            }

            $model = new static($r, $setRelations);
            if($table !== "") {
                $model->_table = $table;
            }

            $return[] = $model;
        }

        return $return;
    }

    /**
     * Returns all records from database.
     *
     * @param array  $where        Where clause array.
     * @param string $table        Table that will be used to get the records from.
     * @param bool   $setRelations Whether relations should be processed or not.
     *
     * @return Model[] Records from database.
     */
    public static function all(array $where = [], string $table = "", bool $setRelations = true) : array
    {
        $query = new QueryBuilder(self::$_connection);
        $model = new static();

        $query->select("*");

        if($table === "") {
            $query->from($model->getTable());
        } else {
            $query->from($table);
        }

        $query = static::_setWhere($where, $query, $model->_primaryKey);

        $result = $query->execute();
        $return = [];

        foreach($result as $r) {
            $model = new static($r, $setRelations);
            if($table !== "") {
                $model->_table = $table;
            }

            $return[] = $model;
        }

        return $return;
    }

    /**
     * Returns the latest records from database.
     *
     * @param int    $length       Length of the array.
     * @param string $column       Column to order the records (default = "id").
     * @param string $table        Table that will be used to get the records from.
     * @param bool   $setRelations Whether relations should be processed or not.
     *
     * @return Model[] Records from database.
     */
    public static function latest(int $length = 10, string $column = "id", string $table = "", bool $setRelations = true) : array
    {
        $query = new QueryBuilder(self::$_connection);
        $model = new static();

        $query->select("*");

        if($table === "") {
            $query->from($model->getTable());
        } else {
            $query->from($table);
        }

        $query->order($column)
              ->limit($length);

        $result = $query->execute();
        $return = [];

        foreach($result as $r) {
            $model = new static($r, $setRelations);
            if($table !== "") {
                $model->_table = $table;
            }

            $return[] = $model;
        }

        return $return;
    }

    /**
     * Sets the WHERE param to the query.
     *
     * @param int|array    $where WHERE param.
     * @param QueryBuilder $query Query to build.
     * @param string       $primaryKey Table primary key.
     *
     * @return QueryBuilder Query object.
     */
    private static function _setWhere($where, QueryBuilder $query, string $primaryKey = "id") : QueryBuilder
    {
        if(!is_array($where)) {
            return $query->where([
                $primaryKey => $where
            ]);
        }

        $columns = $where;

        unset($columns["ORDER BY"]);
        unset($columns["LIMIT"]);
        unset($columns["OFFSET"]);

        if(!empty($columns)) {
            $query->where($columns);
        }

        if(isset($where["ORDER BY"])) {
            $order = $where["ORDER BY"];
            if(!is_array($order)) {
                $order = [$order];
            }

            $query->order(... $order);
        }
        if(isset($where["LIMIT"])) {
            $limit = $where["LIMIT"];
            if(!is_array($limit)) {
                $limit = [$limit];
            }

            $query->limit(... $limit);
        }
        if(isset($where["OFFSET"])) {
            $query->offset($where["OFFSET"]);
        }

        return $query;
    }

    ///////////////////////////////////////
    // End Static methods and properties //
    ///////////////////////////////////////

    /**
     * Whether the current object is a new record or not.
     *
     * @var bool
     */
    private $_isInsert = false;

    /**
     * Table name.
     *
     * @var string
     */
    protected $_table = "";

    /**
     * Primary key name.
     *
     * @var string
     */
    protected $_primaryKey = "id";

    /**
     * Database columns.
     *
     * @var array
     */
    protected $_data = [];

    /**
     * Changed columns.
     *
     * @var array
     */
    protected $_changed = [];

    /**
     * Constructor.
     *
     * @param array|null $columns      Record columns, if `null` it will assume is a new record.
     * @param bool       $setRelations Whether the relations array should be processed or not.
     */
    public function __construct($columns = null, bool $setRelations = false)
    {
        if($columns == null) {
            $this->_isInsert = true;

            return;
        }

        $this->_data = $columns;

        if($setRelations) {
            $this->_setRelations();
        }

        $this->onInstance();
    }

    /**
     * On instance method.
     *
     * Is executed once the constructor has finished.
     */
    public function onInstance()
    {

    }

    /**
     * Sets ORM relations.
     *
     * It processes the `$_relations` array and sets the objects from
     * the database.
     *
     * The array contains the list of the relations. Each index can be
     * either a string being the name of Model class representing the table
     * for the relation, or an array if you need to modify some parameters.
     * If the index is an array, the key must be the name of the Model class,
     *
     * The name of the Model class can start with the prefix sent to the
     * `initialize` method, or the name of table.
     *
     * Example:
     *
     * ```php
     * class User extends Model
     * {
     *     protected static $_relations = [
     *         "Message" => [
     *             "type" => "has_many"
     *         ],
     *         "Configuration"
     *     ]
     * }
     * ```
     *
     * This example will make two relations:
     *
     *  * One for the table `messages`
     *  * One for the table `configurations`
     *
     * The relation for the `messages` table will load all records
     * from the database that matches the local/foreign key relation.
     *
     * By default, the local key is the name of the local table followed by
     * the primary key of the foreign table, and the foreign key is the
     * primary key of the foreign table.
     *
     * So, given that `User and `Messages` follows the standards of this class
     * the local key would be `messages_id` and the foreign key would
     * be `id`, so the generated query would be `SELECT * FROM messages WHERE id=users.messages_id;`.
     *
     * For overriding the default local key change the index `localKey` and for
     * overriding the default foreign key, change the index `foreignKey`.
     *
     * As the message have a sender and a recipient, assuming that the `id` column
     * on the `messages` table is the `messages_id` of the user is wrong, so instead
     * we change the foreign key to a more suitable one: `to_users_id`
     *
     * ```php
     * class User extends Model
     * {
     *     protected static $_relations = [
     *         "Message" => [
     *             "type"       => "has_many",
     *             "foreignKey" => "to_users_id"
     *         ],
     *         "Configuration"
     *     ]
     * }
     * ```
     *
     * By default all the relations are `one to one`, meaning that only one
     * from the database will be fetched.
     *
     * As the user might have more than one message, we change the relation
     * type by changing the `type` index in the value.
     *
     * After this, we are able to access to all messages sent to the user
     * through the property `$user->Message` which would be an array with
     * all `Message` classes representing the records from the database.
     *
     * However, calling that property `Message` isn't the best option since it's
     * not a single message, but a collection of messages.
     * We can change this name by setting the `name` index to something different.
     *
     * ```php
     * class User extends Model
     * {
     *     protected static $_relations = [
     *         "Message" => [
     *             "type"       => "has_many",
     *             "foreignKey" => "to_users_id",
     *             "name"       => "Messages"
     *         ],
     *         "Configuration"
     *     ]
     * }
     * ```
     *
     * Now all messages are in the `$user->Messages` property.
     *
     * Another thing that we would like to change is the amount of records
     * to retrieve from the database and Whether the instanced models should process
     * their relations too. We can do this by changing the index `amount` and `setRelations`
     * respectively.
     *
     * Finally, we can decide if we should retrieve the messages only if the
     * user has verified his email, we do this with the index `condition`, which
     * is a closure that should return a boolean telling if the relation should be
     * processed or not.
     *
     * The closure must accept as parameter an array that will contain the
     * result of the query.
     *
     * ```php
     * class User extends Model
     * {
     *     protected static $_relations = [
     *         "Message" => [
     *             "type"       => "has_many",
     *             "foreignKey" => "to_users_id",
     *             "name"       => "Messages",
     *             "condition"  => "User::canSetMessage"
     *         ],
     *         "Configuration"
     *     ];
     *
     *     public static function canSetMessage(array $columns) : bool
     *     {
     *         // Check that the user has verified his email
     *         if(!$this->email_isVerified) {
     *             return false;
     *         }
     *
     *         // Check that the message isn't deleted by the user
     *         if($columns["to_isDeleted"]) {
     *             return false;
     *         }
     *
     *         return true;
     *     }
     * }
     * ```
     *
     * For more information about the possible values of the array see
     * the documentation for the `$_relations` property.
     */
    protected function _setRelations()
    {
        foreach(static::$_relations as $class => $options) {
            $result = [];

            // Check that `$class` is the actual class name and `$options` is
            // the actual configuration array.
            if(!is_array($options)) {
                $class   = $options;
                $options = [];
            }

            $table = "";
            if(!Str::startsWith($class, static::$_baseNamespace)) {
                $class = static::$_baseNamespace . $class;
            }

            if(!class_exists($class)) {
                $table = substr($class, strlen(static::$_baseNamespace), strlen($class));
                $class = "\\Alexya\\Database\\ORM\\Model";
            }

            /**
             * Local table.
             *
             * @var Model $local
             */
            $local = new static();

            /**
             * Foreign table.
             *
             * @var Model $foreign
             */
            $foreign = new $class();

            // Normalize `$options` array
            // Relation type (has_one, has_many)
            if(empty($options["type"])) {
                $options["type"] = "has_one";
            }
            // Name of the property to create
            if(empty($options["name"])) {
                $options["name"] = str_replace(static::$_baseNamespace, "", $class);
                $options["name"] = explode("\\", $class);
                $options["name"] = $options["name"][count($options["name"]) - 1];
            }
            // Whether the instanced models should process their relations
            if(empty($options["setRelations"])) {
                $options["setRelations"] = false;
            }
            // Amount of records to get
            if(empty($options["amount"])) {
                $options["amount"] = -1;
            }
            // Model class representing the table
            if(empty($options["class"])) {
                $options["class"] = $class;
            }
            // Local key (foreign_table_local_primary_key)
            if(empty($options["localKey"])) {
                $options["localKey"] = $foreign->getTable()."_".$local->_primaryKey;
                if($table != "") {
                    $options["localKey"] = $table."_".$local->_primaryKey;
                }
            }
            // foreign key (foreign_primary_key)
            if(empty($options["foreignKey"])) {
                $options["foreignKey"] = $foreign->_primaryKey;
            }

            // Retrieve the records from the database.
            if($options["type"] === "has_one") {
                // `has_one` relations have only one record
                // Find it given the fact that the `foreignKey` should be
                // the value as `localKey`
                $result = ($options["class"])::find([
                    $options["foreignKey"] => $this->_data[$options["localKey"]]
                ], -1, $table, $options["setRelations"]);
            } else if($options["type"] === "has_many") {
                // `has_many` relations have many records,
                // Retrieve all of them, getting the specified amount of records
                // and where the `foreignKey` is the same as `localKey`
                $res = ($class)::find([
                    $options["foreignKey"] => $this->_data[$options["localKey"]]
                ], $options["amount"], $table, $options["setRelations"]);

                $result = $res;
                // Now that we have all records that matches the local/foreign key
                // we must process them so they passes user defined condition
                if(
                    !empty($options["condition"]) &&
                    is_callable($options["condition"])
                ) {
                    foreach($res as $r) {
                        if(!$options["condition"]($r)) {
                            continue;
                        }

                        $result[] = $r;
                    }
                }
            }

            // Now, the user might have specified a different class
            // for instancing, so just do it.
            $this->_data[$options["name"]] = $result;
            if($options["class"] !== $class) {
                $this->_data[$options["name"]] = new $options["class"]($result);
            }
        }
    }

    /**
     * Returns a column.
     *
     * @param string $name Column name.
     *
     * @return mixed Column's value.
     */
    public function get(string $name)
    {
        return ($this->_data[$name] ?? "");
    }

    /**
     * Sets a column.
     *
     * @param string $name  Column name.
     * @param string $value Column value.
     */
    public function set(string $name, $value)
    {
        $this->_data[$name] = $value;

        if(!is_object($value)) {
            $this->_changed[$name] = $value;
        }
    }

    /**
     * Returns a column.
     *
     * @param string $name Column name.
     *
     * @return mixed Column's value.
     */
    public function __get(string $name)
    {
        return $this->get($name);
    }

    /**
     * Sets a column.
     *
     * @param string $name  Column name.
     * @param string $value Column value.
     */
    public function __set(string $name, $value)
    {
        $this->set($name, $value);
    }

    /**
     * Saves the changes to the database.
     */
    public function save()
    {
        $query = new QueryBuilder(self::$_connection);

        if($this->_isInsert) {
            $query->insert($this->getTable())
                  ->values($this->_changed);

            $id = self::$_connection->insert($query->getQuery());
            $this->_data[$this->_primaryKey] = $id;

            $this->_isInsert = false;
            $this->_changed  = [];

            return;
        }

        $query->update($this->getTable())
              ->set($this->_changed)
              ->where([
                  $this->_primaryKey => $this->_data[$this->_primaryKey]
              ])
              ->execute();

        $this->_changed = [];
    }

    /**
     * Builds and returns table name.
     *
     * @return string Table name.
     */
    public function getTable() : string
    {
        if(!empty($this->_table)) {
            return $this->_table;
        }

        $class = explode("\\", str_replace(self::$_baseNamespace, "", "\\".get_called_class()));
        $table = Str::snake(Str::plural($class));

        $this->_table = $table;

        return $this->_table;
    }

    /**
     * Returns table values.
     *
     * @param bool $decodeJSON Whether the JSONs should be decoded or not.
     *
     * @return array Table values.
     */
    public function asArray(bool $decodeJSON = true) : array
    {
        if(!$decodeJSON) {
            return $this->_data;
        }

        $return = [];
        foreach($this->_data as $key => $value) {
            $decoded = json_decode($value);

            $return[$key] = $decoded;
            if(
                !is_array($decoded)  &&
                !is_object($decoded)
            ) {
                $return[$key] = $value;
            }
        }

        return $return;
    }

    /**
     * Returns values as a JSON.
     *
     * @return string Encoded JSON.
     */
    public function asJSON() : string
    {
        $fields = [];

        foreach($this->_data as $key => $value) {
            if(!is_object($value)) {
                $fields[$key] = $value;
            }
        }

        return json_encode($fields);
    }
}

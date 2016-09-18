<?php
namespace Alexya\Database\ORM;

use \Alexya\Database\{
    Connection,
    QueryBuilder
};

use \Alexya\Tools\Str;

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
 *     $connection = new Connection("localhost", 3306, "root", "", "alexya");
 *     Model::initialize($connection, "\Application\ORM");
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
 *     class UsersTable extends Model
 *     {
 *         protected $_table = "users"; // Without this, the table name would be `userstables`, see \Alexya\Database\ORM\Model::getTable
 *     }
 *
 * The primary key is, by default, `id`, if you want to override it change the property `_primaryKey`
 * with the name of the primary key:
 *
 *     class UsersTable extends Model
 *     {
 *         protected $_primaryKey = "userID";
 *     }
 *
 * The method `onInstance` is executed when the class has been instantiated, use it instead of the constructor.
 *
 * The method `find` finds a record from the database and returns an instance of the Model class.
 * It accepts as parameter an integer being the value of the primary key or an array contaning the
 * `WHERE` clause of the query:
 *
 *     $user = UsersTable::find(1); // SELECT * FROM `users` WHERE `userID`=1 LIMIT 1
 *     $user = UsersTable::find([
 *         "AND" => [
 *             "username" => "test",
 *             "password" => "test"
 *         ]
 *     ]); // SELECT * FROM `users` WHERE `username`='test' AND `password`='test' LIMIT 1
 *
 * You can send a second integer parameter being the amount of records to fetch from the database.
 * If it's omited it will return a single record, otherwise an array of speficied amount of records.
 *
 * To create a record use the method `create` which returns an instance of the Model class or instance a
 * new object directly:
 *
 *     $newUser = UsersTable::create(); // same as `$newUser = new UsersTable();`
 *
 *     $newUser->username = "foo";
 *     $newUser->password = "bar";
 *
 * To save the changes in the database use the method `save`.
 *
 *     $newUser->save(); // INSERT INTO `users`(`username`, `password`) VALUES ('foo', 'bar')
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
     * @var \Alexya\Database\Connection
     */
    private static $_connection;

    /**
     * Base namespace of the ORM classes.
     *
     * @var string
     */
    private static $_baseNamespace = "\\";

    /**
     * Initializes the class.
     *
     * @param \Alexya\Database\Connection $connection    Database connection.
     * @param string                      $baseNamespace Base namespace of the ORM classes.
     */
    public static function initialize(Connection $connection, string $baseNamespace = "\\")
    {
        self::$_connection    = $connection;
        self::$_baseNamespace = Str::trailing($baseNamespace, "\\");
    }

    /**
     * Returns a new instance of the class.
     *
     * @param string $table Table name.
     *
     * @return \Alexya\Database\ORM\Model Instance of the class.
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
     * @param string $table Table that the model is representing.
     *
     * @return \Alexya\Database\ORM\Model Record instance.
     */
    public static function create(string $table = "") : Model
    {
        $model = new static();
        $model->_table = $table;

        return $model
    }

    /**
     * Finds and returns one or more record from the database.
     *
     * @param int|string|array $id    Primary key value or `WHERE` clause.
     * @param int              $limit Amount of records to retrieve from database,
     *                                if `-1` an instance of the Model class will be returned.
     * @param string           $table Table that will be used to get the records from.
     *
     * @return \Alexya\Database\ORM\Model|array Records from the database.
     */
    public static function find($id, int $limit = -1, string $table = "")
    {
        $query = new QueryBuilder(self::$_connection);
        $model = new static();

        $query->select("*");

        if($table == "") {
            $query->from($model->getTable());
        } else {
            $query->from($table)
        }

        if(is_numeric($id)) {
            $query->where([
                $model->_primaryKey => $id
            ]);
        } else {
            $query->where($id);
        }

        if($limit < 0) {
            $query->limit(1);
        } else {
            $query->limit($limit);
        }

        $result = $query->execute();
        $return = [];

        foreach($result as $r) {
            if($limit < 0) {
                return new static($r);
            }

            $return[] = new static($r);
        }

        return $return;
    }

    /**
     * Returns all records from database.
     *
     * @param string $table Table that will be used to get the records from.
     *
     * @return array Records from database.
     */
    public static function all(string $table = "") : array
    {
        $query = new QueryBuilder(self::$_connection);
        $model = new static();

        $query->select("*");

        if($table == "") {
            $query->from($model->getTable());
        } else {
            $query->from($table)
        }

        $result = $query->execute();
        $return = [];

        foreach($result as $r) {
            $return[] = new static($r);
        }

        return $return;
    }

    /**
     * Retruns the latest records from database.
     *
     * @param int    $length Length of the array.
     * @param string $column Column to order the records (default = "id").
     * @param string $table  Table that will be used to get the records from.
     *
     * @return array Records from database.
     */
    public static function latest(int $length = 10, string $column = "id", string $table = "") : array
    {
        $query = new QueryBuilder(self::$_connection);
        $model = new static();

        $query->select("*");

        if($table == "") {
            $query->from($model->getTable());
        } else {
            $query->from($table)
        }

        $query->orderBy($column)
              ->limit($length);

        $result = $query->execute();
        $return = [];

        foreach($result as $r) {
            $return[] = new static($r);
        }

        return $return;
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
     * Constructor.
     *
     * @param array|null $columns Record columns, if `null` it will assume is a new record.
     */
    public function __construct($columns = null)
    {
        if($columns == null) {
            $this->_isInsert = true;

            return;
        }

        $this->_data = $columns;

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
     * Returns a column.
     *
     * @param string $name Column name.
     *
     * @return mixed Column's value.
     */
    public function get(string $name)
    {
        return $this->_data[$name];
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
        return $this->set($name, $value);
    }

    /**
     * Saves the changes to the database.
     */
    public function save()
    {
        $query = new QueryBuilder(self::$_connection);

        if($this->_isInsert) {
            $query->insert($this->getTable())
                  ->value($this->_data);
        } else {
            $query->update($this->getTable())
                  ->set($this->_data)
                  ->where([
                      $this->_primaryKey = $this->_data[$this->_primaryKey]
                  ]);
        }

        $query->execute();
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
}

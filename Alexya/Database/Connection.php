<?php
namespace Alexya\Database;

use \Alexya\Database\Exceptions\{
    ConnectionFailed,
    QueryFailed
};

use \PDO;
use \PDOException;

/**
 * Connection class.
 *
 * This class stabilises a connection to a database to perform queries.
 *
 * The constructor accepts the following parameters:
 *
 *  * A string being server's host/ip
 *  * An integer being server's port
 *  * A string being database username
 *  * A string being database password
 *  * A string being database password
 *
 * @author Manulaiko <manulaiko@gmail.com>
 */
class Connection
{
    ///////////////////////////////
    // Start connection settings //
    ///////////////////////////////
    /**
     * Database host.
     *
     * @var string
     */
    private $_host = "";

    /**
     * Server port.
     *
     * @var int
     */
    private $_port = 3306;

    /**
     * Server type.
     *
     * @var string
     */
    private $_type = "mysql";

    /**
     * Username.
     *
     * @var string
     */
    private $_username = "";

    /**
     * Password.
     *
     * @var string
     */
    private $_password = "";

    /**
     * Database name.
     *
     * @var string
     */
    private $_database = "";

    /**
     * Alternative PDO options.
     *
     * @var array
     */
    private $_options = [];
    /////////////////////////////
    // End connection settings //
    /////////////////////////////

    /**
     * Connection handler.
     *
     * @var PDO
     */
    private $_connection = null;

    /**
     * Last query executed.
     *
     * @var string
     */
    public $lastQuery = "";

    /**
     * Constructor.
     *
     * @param string $host     Database host.
     * @param int    $port     Database port.
     * @param string $username Database username.
     * @param string $password Database password.
     * @param string $database Database name.
     * @param string $type     Database type (default is "mysql")
     * @param array  $options  Alternative PDO options.
     */
    public function __construct(string $host, int $port, string $username, string $password, string $database, string $type = "mysql", array $options = [])
    {
        $this->_host     = $host;
        $this->_port     = $port;
        $this->_username = $username;
        $this->_password = $password;
        $this->_database = $database;
        $this->_type     = $type;
        $this->_options  = $options;

        $this->connect();
    }

    /**
     * Closes connection.
     */
    public function close()
    {
        $this->_connection = null;
    }

    /**
     * Connects to the server.
     *
     * @throws ConnectionFailed If couldn't connect to host
     */
    public function connect()
    {
        try {
            $this->_connection = new PDO(
                $this->_type .":host=". $this->_host .";dbname=". $this->_database .";port=". $this->_port,
                $this->_username,
                $this->_password
            );
        } catch(PDOException $e) {
            $code    = $e->getCode();
            $message = $e->getMessage();

            throw new ConnectionFailed($code, $message);
        }
    }

    /**
     * Checks whether connection is closed or not.
     *
     * @return bool Whether connection is closed or not.
     */
    public function isClosed() : bool
    {
        return ($this->_connection === null);
    }

    /**
     * Returns the PDO object with current connection.
     *
     * @return PDO PDO object.
     */
    public function getConnection() : PDO
    {
        return $this->_connection;
    }

    /**
     * Returns last error message.
     *
     * @return string Last error message.
     */
    public function getError() : string
    {
        return ($this->_connection->errorInfo()[2] ?? "undefined");
    }

    /**
     * Executes a query.
     *
     * @param string $query    SQL query to execute.
     * @param bool   $fetchAll Whether all results should be fetched or not (default = `true`).
     * @param int    $fetch    How results should be fetched (default = `PDO::FETCH_ASSOC`).
     *
     * @return mixed Query result.
     *
     * @throws QueryFailed If the query failed to execute.
     */
    public function execute(string $query, bool $fetchAll = true, int $fetch = PDO::FETCH_ASSOC)
    {
        $result = $this->_connection->query($query);

        if($result === false) {
            throw new QueryFailed($query, $this->_connection->errorInfo()[2]);
        }

        $this->lastQuery = $query;

        if($fetchAll) {
            return $result->fetchAll($fetch);
        }

        return $result->fetch($fetch);
    }

    /**
     * Executes an INSERT query
     *
     * @param string $query    SQL query to execute.
     * @param bool   $fetchAll Whether all results should be fetched or not (default = `true`).
     * @param int    $fetch    How results should be fetched (default = `PDO::FETCH_ASSOC`).
     *
     * @return int Last inserted ID.
     */
    public function insert() : int
    {
        $this->execute(... func_get_args());

        return $this->_connection->lastInsertId();
    }
}

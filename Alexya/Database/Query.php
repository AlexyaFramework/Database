<?php
namespace Alexya\Database;

/**
 * Query class.
 *
 * This is the base class for all query builders.
 *
 * The constructor accepts as parameter the Connection object to execute the query.
 *
 * Once you've build the query use the method `execute` to execute it.
 *
 * @author Manulaiko <manulaiko@gmail.com>
 */
abstract class QueryFailed
{
    /**
     * Connection object.
     *
     * @var \Alexya\Database\Connection
     */
    private $_connection;

    /**
     * Constructor.
     *
     * @param \Alexya\Database\Connection $connection Database connection.
     */
    public function __construct(Connection $connection)
    {
        $this->_connection = $connection;
    }

    /**
     * Executes the query.
     *
     * @return mixed Query result.
     */
    public function execute()
    {
        return $this->_connection->execute($this->compile());
    }

    /**
     * Compiles and returns the query.
     *
     * @return string Query as SQL.
     */
    public abstract function complie() : string;
}

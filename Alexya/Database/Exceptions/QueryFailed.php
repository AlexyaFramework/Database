<?php
namespace Alexya\Database\Exceptions;

/**
 * Query failed exception.
 *
 * Occurs when couldn't execute a query.
 *
 * @author Manulaiko <manulaiko@gmail.com>
 */
class QueryFailed extends \Exception
{
    /**
     * Constructor.
     *
     * @param string $query   Executed query.
     * @param string $message Error message.
     */
    public function __construct(string $query, string $message)
    {
        parent::__construct("Couldn't execute the query '{$query}': {$message}");
    }
}

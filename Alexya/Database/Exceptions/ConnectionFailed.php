<?php
namespace Alexya\Database\Exceptions;

/**
 * Connection failed exception.
 *
 * Occurs when couldn't connect to Database.
 *
 * @author Manulaiko <manulaiko@gmail.com>
 */
class ConnectionFailed extends \Exception
{
    /**
     * Constructor.
     *
     * @param int    $code    Error code.
     * @param string $message Error message.
     */
    public function __construct(int $code, string $message)
    {
        parent::__construct("Couldn't connect to database: -{$code} {$message}");
    }
}

<?php declare(strict_types=1);

namespace Helioviewer\Api\Request;

/**
 * Collect our request parameters and merge them 
 */
class RequestException extends \Exception
{
    /**
     * Construct request exception exception. 
     * @param string $message exception message to throw.
     * @param int $code The Exception code.
     */
    public function __construct(string $message = "", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}



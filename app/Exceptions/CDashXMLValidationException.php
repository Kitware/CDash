<?php

namespace App\Exceptions;

use Exception;
use RuntimeException;

class CDashXMLValidationException extends RuntimeException
{
    /**
     * @param array<int,string> $message
     */
    public function __construct(array $message = [], int $code = 0, Exception $previous = null)
    {
        $encoded_msg = json_encode($message);
        $encoded_msg = $encoded_msg===false ? "" : $encoded_msg;
        parent::__construct($encoded_msg, $code, $previous);
    }

    /**
     * @return array<int,string>
     */
    public function getDecodedMessage(bool $assoc = false): array
    {
        $decoded_msg = json_decode($this->getMessage(), $assoc);
        if (!isset($decoded_msg) || is_bool($decoded_msg)) {
            $decoded_msg = ["An XML validation error has occurred!"];
        }
        return $decoded_msg;
    }
}

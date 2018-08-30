<?php

namespace IronCore;

/**
 * HttpException
 *
 * The Http_Exception class represents an HTTP response status that is not 200 OK.
 *
 * @package IronCore
 * @author Tino Ehrich (tino@bigpun.me)
 */
class HttpException extends \Exception
{
    const NOT_MODIFIED        = 304;
    const BAD_REQUEST         = 400;
    const NOT_FOUND           = 404;
    const NOT_ALLOWED         = 405;
    const CONFLICT            = 409;
    const PRECONDITION_FAILED = 412;
    const INTERNAL_ERROR      = 500;
    const SERVICE_UNAVAILABLE = 503;
    const GATEWAY_TIMEOUT     = 504;
}

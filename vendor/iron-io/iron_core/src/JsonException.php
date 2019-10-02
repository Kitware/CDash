<?php

namespace IronCore;

/**
 * JsonException
 *
 * The JSON_Exception class represents an failures of decoding json strings.
 *
 * @package IronCore
 * @author Tino Ehrich (tino@bigpun.me)
 */
class JSONException extends \Exception
{
    public $error      = null;
    public $error_code = JSON_ERROR_NONE;

    public function __construct($error_code)
    {
        $this->error_code = $error_code;
        switch ($error_code) {
            case JSON_ERROR_DEPTH:
                $this->error = 'Maximum stack depth exceeded.';
                break;
            case JSON_ERROR_CTRL_CHAR:
                $this->error = "Unexpected control characted found.";
                break;
            case JSON_ERROR_SYNTAX:
                $this->error = "Syntax error, malformed JSON";
                break;
            default:
                $this->error = $error_code;
                break;

        }
        parent::__construct();
    }

    public function __toString()
    {
        return $this->error;
    }
}

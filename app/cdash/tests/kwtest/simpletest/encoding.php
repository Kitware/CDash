<?php

/**
 *  base include file for SimpleTest
 *
 * @version    $Id$
 */

/**#@+
 *  include other SimpleTest class files
 */
require_once dirname(__FILE__) . '/socket.php';
/**#@-*/

/**
 *    Single post parameter.
 */
class SimpleEncodedPair
{
    private $key;
    private $value;

    /**
     *    Stashes the data for rendering later.
     *
     * @param string $key form element name
     * @param string $value data to send
     */
    public function __construct($key, $value)
    {
        $this->key = $key;
        $this->value = $value;
    }

    /**
     *    The pair as a single string.
     *
     * @return string encoded pair
     */
    public function asRequest()
    {
        return urlencode($this->key) . '=' . urlencode($this->value);
    }

    /**
     *    The MIME part as a string.
     *
     * @return string MIME part encoding
     */
    public function asMime()
    {
        $part = 'Content-Disposition: form-data; ';
        $part .= 'name="' . $this->key . "\"\r\n";
        $part .= "\r\n" . $this->value;
        return $part;
    }

    /**
     *    Is this the value we are looking for?
     *
     * @param string $key identifier
     *
     * @return bool true if matched
     */
    public function isKey($key)
    {
        return $key == $this->key;
    }

    /**
     *    Is this the value we are looking for?
     *
     * @return string identifier
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     *    Is this the value we are looking for?
     *
     * @return string content
     */
    public function getValue()
    {
        return $this->value;
    }
}

/**
 *    Single post parameter.
 */
class SimpleAttachment
{
    private $key;
    private $content;
    private $filename;

    /**
     *    Stashes the data for rendering later.
     *
     * @param string $key key to add value to
     * @param string $content raw data
     * @param hash $filename original filename
     */
    public function __construct($key, $content, $filename)
    {
        $this->key = $key;
        $this->content = $content;
        $this->filename = $filename;
    }

    /**
     *    The pair as a single string.
     *
     * @return string encoded pair
     */
    public function asRequest()
    {
        return '';
    }

    /**
     *    The MIME part as a string.
     *
     * @return string MIME part encoding
     */
    public function asMime()
    {
        $part = 'Content-Disposition: form-data; ';
        $part .= 'name="' . $this->key . '"; ';
        $part .= 'filename="' . $this->filename . '"';
        $part .= "\r\nContent-Type: " . $this->deduceMimeType();
        $part .= "\r\n\r\n" . $this->content;
        return $part;
    }

    /**
     *    Attempts to figure out the MIME type from the
     *    file extension and the content.
     *
     * @return string MIME type
     */
    protected function deduceMimeType()
    {
        if ($this->isOnlyAscii($this->content)) {
            return 'text/plain';
        }
        return 'application/octet-stream';
    }

    /**
     *    Tests each character is in the range 0-127.
     *
     * @param string $ascii string to test
     */
    protected function isOnlyAscii($ascii)
    {
        for ($i = 0, $length = strlen($ascii); $i < $length; $i++) {
            if (ord($ascii[$i]) > 127) {
                return false;
            }
        }
        return true;
    }

    /**
     *    Is this the value we are looking for?
     *
     * @param string $key identifier
     *
     * @return bool true if matched
     */
    public function isKey($key)
    {
        return $key == $this->key;
    }

    /**
     *    Is this the value we are looking for?
     *
     * @return string identifier
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     *    Is this the value we are looking for?
     *
     * @return string content
     */
    public function getValue()
    {
        return $this->filename;
    }
}

/**
 *    Bundle of GET/POST parameters. Can include
 *    repeated parameters.
 */
class SimpleEncoding
{
    private $request;

    /**
     *    Starts empty.
     *
     * @param array $query Hash of parameters.
     *                     Multiple values are
     *                     as lists on a single key.
     */
    public function __construct($query = false)
    {
        if (!$query) {
            $query = [];
        }
        $this->clear();
        $this->merge($query);
    }

    /**
     *    Empties the request of parameters.
     */
    public function clear()
    {
        $this->request = [];
    }

    /**
     *    Adds a parameter to the query.
     *
     * @param string $key key to add value to
     * @param string /array $value    New data
     */
    public function add($key, $value)
    {
        if ($value === false) {
            return;
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                $this->addPair($key, $item);
            }
        } else {
            $this->addPair($key, $value);
        }
    }

    /**
     *    Adds a new value into the request.
     *
     * @param string $key key to add value to
     * @param string /array $value    New data
     */
    protected function addPair($key, $value)
    {
        $this->request[] = new SimpleEncodedPair($key, $value);
    }

    /**
     *    Adds a MIME part to the query. Does nothing for a
     *    form encoded packet.
     *
     * @param string $key key to add value to
     * @param string $content raw data
     * @param hash $filename original filename
     */
    public function attach($key, $content, $filename)
    {
        $this->request[] = new SimpleAttachment($key, $content, $filename);
    }

    /**
     *    Adds a set of parameters to this query.
     *
     * @param array /SimpleQueryString $query  Multiple values are
     *                                           as lists on a single key
     */
    public function merge($query)
    {
        if (is_object($query)) {
            $this->request = array_merge($this->request, $query->getAll());
        } elseif (is_array($query)) {
            foreach ($query as $key => $value) {
                $this->add($key, $value);
            }
        }
    }

    /**
     *    Accessor for single value.
     *
     * @return string/array    False if missing, string
     *                            if present and array if
     *                            multiple entries
     */
    public function getValue($key)
    {
        $values = [];
        foreach ($this->request as $pair) {
            if ($pair->isKey($key)) {
                $values[] = $pair->getValue();
            }
        }
        if (count($values) == 0) {
            return false;
        } elseif (count($values) == 1) {
            return $values[0];
        } else {
            return $values;
        }
    }

    /**
     *    Accessor for listing of pairs.
     *
     * @return array all pair objects
     */
    public function getAll()
    {
        return $this->request;
    }

    /**
     *    Renders the query string as a URL encoded
     *    request part.
     *
     * @return string part of URL
     */
    protected function encode()
    {
        $statements = [];
        foreach ($this->request as $pair) {
            if ($statement = $pair->asRequest()) {
                $statements[] = $statement;
            }
        }
        return implode('&', $statements);
    }
}

/**
 *    Bundle of GET parameters. Can include
 *    repeated parameters.
 */
class SimpleGetEncoding extends SimpleEncoding
{
    /**
     *    Starts empty.
     *
     * @param array $query Hash of parameters.
     *                     Multiple values are
     *                     as lists on a single key.
     */
    public function __construct($query = false)
    {
        parent::__construct($query);
    }

    /**
     *    HTTP request method.
     *
     * @return string always GET
     */
    public function getMethod()
    {
        return 'GET';
    }

    /**
     *    Writes no extra headers.
     *
     * @param SimpleSocket $socket socket to write to
     */
    public function writeHeadersTo(&$socket)
    {
    }

    /**
     *    No data is sent to the socket as the data is encoded into
     *    the URL.
     *
     * @param SimpleSocket $socket socket to write to
     */
    public function writeTo(&$socket)
    {
    }

    /**
     *    Renders the query string as a URL encoded
     *    request part for attaching to a URL.
     *
     * @return string part of URL
     */
    public function asUrlRequest()
    {
        return $this->encode();
    }
}

/**
 *    Bundle of URL parameters for a HEAD request.
 */
class SimpleHeadEncoding extends SimpleGetEncoding
{
    /**
     *    Starts empty.
     *
     * @param array $query Hash of parameters.
     *                     Multiple values are
     *                     as lists on a single key.
     */
    public function __construct($query = false)
    {
        parent::__construct($query);
    }

    /**
     *    HTTP request method.
     *
     * @return string always HEAD
     */
    public function getMethod()
    {
        return 'HEAD';
    }
}

/**
 *    Bundle of URL parameters for a DELETE request.
 */
class SimpleDeleteEncoding extends SimpleGetEncoding
{
    /**
     *    Starts empty.
     *
     * @param array $query Hash of parameters.
     *                     Multiple values are
     *                     as lists on a single key.
     */
    public function __construct($query = false)
    {
        parent::__construct($query);
    }

    /**
     *    HTTP request method.
     *
     * @return string always DELETE
     */
    public function getMethod()
    {
        return 'DELETE';
    }
}

/**
 *    Bundles an entity-body for transporting
 *    a raw content payload with the request.
 */
class SimpleEntityEncoding extends SimpleEncoding
{
    private $content_type;
    private $body;

    public function __construct($query = false, $content_type = false)
    {
        $this->content_type = $content_type;
        if (is_string($query)) {
            $this->body = $query;
            parent::__construct();
        } else {
            parent::__construct($query);
        }
    }

    /**
     *    Returns the media type of the entity body
     *
     * @return string
     */
    public function getContentType()
    {
        if (!$this->content_type) {
            return ($this->body) ? 'text/plain' : 'application/x-www-form-urlencoded';
        }
        return $this->content_type;
    }

    /**
     *    Dispatches the form headers down the socket.
     *
     * @param SimpleSocket $socket socket to write to
     */
    public function writeHeadersTo(&$socket)
    {
        $socket->write('Content-Length: ' . (int) strlen($this->encode()) . "\r\n");
        $socket->write('Content-Type: ' . $this->getContentType() . "\r\n");
    }

    /**
     *    Dispatches the form data down the socket.
     *
     * @param SimpleSocket $socket socket to write to
     */
    public function writeTo(&$socket)
    {
        $socket->write($this->encode());
    }

    /**
     *    Renders the request body
     *
     * @return Encoded entity body
     */
    protected function encode()
    {
        return ($this->body) ? $this->body : parent::encode();
    }
}

/**
 *    Bundle of POST parameters. Can include
 *    repeated parameters.
 */
class SimplePostEncoding extends SimpleEntityEncoding
{
    /**
     *    Starts empty.
     *
     * @param array $query Hash of parameters.
     *                     Multiple values are
     *                     as lists on a single key.
     */
    public function __construct($query = false, $content_type = false)
    {
        if (is_array($query) and $this->hasMoreThanOneLevel($query)) {
            $query = $this->rewriteArrayWithMultipleLevels($query);
        }
        parent::__construct($query, $content_type);
    }

    public function hasMoreThanOneLevel($query)
    {
        foreach ($query as $key => $value) {
            if (is_array($value)) {
                return true;
            }
        }
        return false;
    }

    public function rewriteArrayWithMultipleLevels($query)
    {
        $query_ = [];
        foreach ($query as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $sub_key => $sub_value) {
                    $query_[$key . '[' . $sub_key . ']'] = $sub_value;
                }
            } else {
                $query_[$key] = $value;
            }
        }
        if ($this->hasMoreThanOneLevel($query_)) {
            $query_ = $this->rewriteArrayWithMultipleLevels($query_);
        }
        return $query_;
    }

    /**
     *    HTTP request method.
     *
     * @return string always POST
     */
    public function getMethod()
    {
        return 'POST';
    }

    /**
     *    Renders the query string as a URL encoded
     *    request part for attaching to a URL.
     *
     * @return string part of URL
     */
    public function asUrlRequest()
    {
        return '';
    }
}

/**
 *    Encoded entity body for a PUT request.
 */
class SimplePutEncoding extends SimpleEntityEncoding
{
    /**
     *    Starts empty.
     *
     * @param array $query Hash of parameters.
     *                     Multiple values are
     *                     as lists on a single key.
     */
    public function __construct($query = false, $content_type = false)
    {
        parent::__construct($query, $content_type);
    }

    /**
     *    HTTP request method.
     *
     * @return string always PUT
     */
    public function getMethod()
    {
        return 'PUT';
    }
}

/**
 *    Bundle of POST parameters in the multipart
 *    format. Can include file uploads.
 */
class SimpleMultipartEncoding extends SimplePostEncoding
{
    private $boundary;

    /**
     *    Starts empty.
     *
     * @param array $query Hash of parameters.
     *                     Multiple values are
     *                     as lists on a single key.
     */
    public function __construct($query = false, $boundary = false)
    {
        parent::__construct($query);
        $this->boundary = ($boundary === false ? uniqid('st') : $boundary);
    }

    /**
     *    Dispatches the form headers down the socket.
     *
     * @param SimpleSocket $socket socket to write to
     */
    public function writeHeadersTo(&$socket)
    {
        $socket->write('Content-Length: ' . (int) strlen($this->encode()) . "\r\n");
        $socket->write('Content-Type: multipart/form-data; boundary=' . $this->boundary . "\r\n");
    }

    /**
     *    Dispatches the form data down the socket.
     *
     * @param SimpleSocket $socket socket to write to
     */
    public function writeTo(&$socket)
    {
        $socket->write($this->encode());
    }

    /**
     *    Renders the query string as a URL encoded
     *    request part.
     *
     * @return string part of URL
     */
    public function encode()
    {
        $stream = '';
        foreach ($this->getAll() as $pair) {
            $stream .= '--' . $this->boundary . "\r\n";
            $stream .= $pair->asMime() . "\r\n";
        }
        $stream .= '--' . $this->boundary . "--\r\n";
        return $stream;
    }
}

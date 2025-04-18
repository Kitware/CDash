<?php

/**
 *  base include file for SimpleTest
 *
 * @version    $Id$
 */

/**#@+
 *  include other SimpleTest class files
 */
require_once dirname(__FILE__) . '/encoding.php';
/**#@-*/

/**
 *    URL parser to replace parse_url() PHP function which
 *    got broken in PHP 4.3.0. Adds some browser specific
 *    functionality such as expandomatics.
 *    Guesses a bit trying to separate the host from
 *    the path and tries to keep a raw, possibly unparsable,
 *    request string as long as possible.
 */
class SimpleUrl
{
    private $scheme;
    private $username;
    private $password;
    private $host;
    private $port;
    public $path;
    private $request;
    private $fragment;
    private $x;
    private $y;
    private $target;
    private $raw = false;

    /**
     *    Constructor. Parses URL into sections.
     *
     * @param string $url incoming URL
     */
    public function __construct($url = '')
    {
        [$x, $y] = $this->chompCoordinates($url);
        $this->setCoordinates($x, $y);
        $this->scheme = $this->chompScheme($url);
        if ($this->scheme === 'file') {
            // Unescaped backslashes not used in directory separator context
            // will get caught by this, but they should have been urlencoded
            // anyway so we don't care. If this ends up being a problem, the
            // host regexp must be modified to match for backslashes when
            // the scheme is file.
            $url = str_replace('\\', '/', $url);
        }
        [$this->username, $this->password] = $this->chompLogin($url);
        $this->host = $this->chompHost($url);
        $this->port = false;
        if (preg_match('/(.*?):(.*)/', $this->host, $host_parts)) {
            if ($this->scheme === 'file' && strlen($this->host) === 2) {
                // DOS drive was placed in authority; promote it to path.
                $url = '/' . $this->host . $url;
                $this->host = false;
            } else {
                $this->host = $host_parts[1];
                $this->port = (int) $host_parts[2];
            }
        }
        $this->path = $this->chompPath($url);
        $this->request = $this->parseRequest($this->chompRequest($url));
        $this->fragment = (strncmp($url, '#', 1) == 0 ? substr($url, 1) : false);
        $this->target = false;
    }

    /**
     *    Extracts the X, Y coordinate pair from an image map.
     *
     * @param string $url URL so far. The coordinates will be
     *                    removed.
     *
     * @return array X, Y as a pair of integers
     */
    protected function chompCoordinates(&$url)
    {
        if (preg_match('/(.*)\?(\d+),(\d+)$/', $url, $matches)) {
            $url = $matches[1];
            return [(int) $matches[2], (int) $matches[3]];
        }
        return [false, false];
    }

    /**
     *    Extracts the scheme part of an incoming URL.
     *
     * @param string $url URL so far. The scheme will be
     *                    removed.
     *
     * @return string scheme part or false
     */
    protected function chompScheme(&$url)
    {
        if (preg_match('#^([^/:]*):(//)(.*)#', $url, $matches)) {
            $url = $matches[2] . $matches[3];
            return $matches[1];
        }
        return false;
    }

    /**
     *    Extracts the username and password from the
     *    incoming URL. The // prefix will be reattached
     *    to the URL after the doublet is extracted.
     *
     * @param string $url URL so far. The username and
     *                    password are removed.
     *
     * @return array Two item list of username and
     *               password. Will urldecode() them.
     */
    protected function chompLogin(&$url)
    {
        $prefix = '';
        if (preg_match('#^(//)(.*)#', $url, $matches)) {
            $prefix = $matches[1];
            $url = $matches[2];
        }
        if (preg_match('#^([^/]*)@(.*)#', $url, $matches)) {
            $url = $prefix . $matches[2];
            $parts = explode(':', $matches[1]);
            return [
                urldecode($parts[0]),
                isset($parts[1]) ? urldecode($parts[1]) : false];
        }
        $url = $prefix . $url;
        return [false, false];
    }

    /**
     *    Extracts the host part of an incoming URL.
     *    Includes the port number part. Will extract
     *    the host if it starts with // or it has
     *    a top level domain or it has at least two
     *    dots.
     *
     * @param string $url URL so far. The host will be
     *                    removed.
     *
     * @return string host part guess or false
     */
    protected function chompHost(&$url)
    {
        if (preg_match('!^(//)(.*?)(/.*|\?.*|#.*|$)!', $url, $matches)) {
            $url = $matches[3];
            return $matches[2];
        }
        if (preg_match('!(.*?)(\.\./|\./|/|\?|#|$)(.*)!', $url, $matches)) {
            $tlds = SimpleUrl::getAllTopLevelDomains();
            if (preg_match('/[a-z0-9\-]+\.(' . $tlds . ')/i', $matches[1])) {
                $url = $matches[2] . $matches[3];
                return $matches[1];
            } elseif (preg_match('/[a-z0-9\-]+\.[a-z0-9\-]+\.[a-z0-9\-]+/i', $matches[1])) {
                $url = $matches[2] . $matches[3];
                return $matches[1];
            }
        }
        return false;
    }

    /**
     *    Extracts the path information from the incoming
     *    URL. Strips this path from the URL.
     *
     * @param string $url URL so far. The host will be
     *                    removed.
     *
     * @return string path part or '/'
     */
    protected function chompPath(&$url)
    {
        if (preg_match('/(.*?)(\?|#|$)(.*)/', $url, $matches)) {
            $url = $matches[2] . $matches[3];
            return $matches[1] ? $matches[1] : '';
        }
        return '';
    }

    /**
     *    Strips off the request data.
     *
     * @param string $url URL so far. The request will be
     *                    removed.
     *
     * @return string raw request part
     */
    protected function chompRequest(&$url)
    {
        if (preg_match('/\?(.*?)(#|$)(.*)/', $url, $matches)) {
            $url = $matches[2] . $matches[3];
            return $matches[1];
        }
        return '';
    }

    /**
     *    Breaks the request down into an object.
     *
     * @param string $raw raw request
     *
     * @return SimpleFormEncoding parsed data
     */
    protected function parseRequest($raw)
    {
        $this->raw = $raw;
        $request = new SimpleGetEncoding();
        foreach (explode('&', $raw) as $pair) {
            if (preg_match('/(.*?)=(.*)/', $pair, $matches)) {
                $request->add(urldecode($matches[1]), urldecode($matches[2]));
            } elseif ($pair) {
                $request->add(urldecode($pair), '');
            }
        }
        return $request;
    }

    /**
     *    Accessor for protocol part.
     *
     * @param string $default value to use if not present
     *
     * @return string Scheme name, e.g "http".
     */
    public function getScheme($default = false)
    {
        return $this->scheme ? $this->scheme : $default;
    }

    /**
     *    Accessor for user name.
     *
     * @return string username preceding host
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     *    Accessor for password.
     *
     * @return string password preceding host
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     *    Accessor for hostname and port.
     *
     * @param string $default value to use if not present
     *
     * @return string hostname only
     */
    public function getHost($default = false)
    {
        return $this->host ? $this->host : $default;
    }

    /**
     *    Accessor for top level domain.
     *
     * @return string last part of host
     */
    public function getTld()
    {
        $path_parts = pathinfo($this->getHost());
        return $path_parts['extension'] ?? false;
    }

    /**
     *    Accessor for port number.
     *
     * @return int TCP/IP port number
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     *    Accessor for path.
     *
     * @return string full path including leading slash if implied
     */
    public function getPath()
    {
        if (!$this->path && $this->host) {
            return '/';
        }
        return $this->path;
    }

    /**
     *    Accessor for page if any. This may be a
     *    directory name if ambiguious.
     *
     * @return Page name
     */
    public function getPage()
    {
        if (!preg_match('/([^\/]*?)$/', $this->getPath(), $matches)) {
            return false;
        }
        return $matches[1];
    }

    /**
     *    Gets the path to the page.
     *
     * @return string path less the page
     */
    public function getBasePath()
    {
        if (!preg_match('/(.*\/)[^\/]*?$/', $this->getPath(), $matches)) {
            return false;
        }
        return $matches[1];
    }

    /**
     *    Accessor for fragment at end of URL after the "#".
     *
     * @return string part after "#"
     */
    public function getFragment()
    {
        return $this->fragment;
    }

    /**
     *    Sets image coordinates. Set to false to clear
     *    them.
     *
     * @param int $x horizontal position
     * @param int $y vertical position
     */
    public function setCoordinates($x = false, $y = false)
    {
        if (($x === false) || ($y === false)) {
            $this->x = $this->y = false;
            return;
        }
        $this->x = (int) $x;
        $this->y = (int) $y;
    }

    /**
     *    Accessor for horizontal image coordinate.
     *
     * @return int x value
     */
    public function getX()
    {
        return $this->x;
    }

    /**
     *    Accessor for vertical image coordinate.
     *
     * @return int y value
     */
    public function getY()
    {
        return $this->y;
    }

    /**
     *    Accessor for current request parameters
     *    in URL string form. Will return teh original request
     *    if at all possible even if it doesn't make much
     *    sense.
     *
     * @return string form is string "?a=1&b=2", etc
     */
    public function getEncodedRequest()
    {
        if ($this->raw) {
            $encoded = $this->raw;
        } else {
            $encoded = $this->request->asUrlRequest();
        }
        if ($encoded) {
            return '?' . preg_replace('/^\?/', '', $encoded);
        }
        return '';
    }

    /**
     *    Adds an additional parameter to the request.
     *
     * @param string $key name of parameter
     * @param string $value value as string
     */
    public function addRequestParameter($key, $value)
    {
        $this->raw = false;
        $this->request->add($key, $value);
    }

    /**
     *    Adds additional parameters to the request.
     *
     * @param hash /SimpleFormEncoding $parameters   Additional
     *                                                parameters
     */
    public function addRequestParameters($parameters)
    {
        $this->raw = false;
        $this->request->merge($parameters);
    }

    /**
     *    Clears down all parameters.
     */
    public function clearRequest()
    {
        $this->raw = false;
        $this->request = new SimpleGetEncoding();
    }

    /**
     *    Gets the frame target if present. Although
     *    not strictly part of the URL specification it
     *    acts as similarily to the browser.
     *
     * @return boolean/string    Frame name or false if none
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     *    Attaches a frame target.
     *
     * @param string $frame name of frame
     */
    public function setTarget($frame)
    {
        $this->raw = false;
        $this->target = $frame;
    }

    /**
     *    Renders the URL back into a string.
     *
     * @return string URL in canonical form
     */
    public function asString()
    {
        $path = $this->path;
        $scheme = $identity = $host = $port = $encoded = $fragment = '';
        if ($this->username && $this->password) {
            $identity = $this->username . ':' . $this->password . '@';
        }
        if ($this->getHost()) {
            $scheme = $this->getScheme() ? $this->getScheme() : 'http';
            $scheme .= '://';
            $host = $this->getHost();
        } elseif ($this->getScheme() === 'file') {
            // Safest way; otherwise, file URLs on Windows have an extra
            // leading slash. It might be possible to convert file://
            // URIs to local file paths, but that requires more research.
            $scheme = 'file://';
        }
        if ($this->getPort() && $this->getPort() != 80) {
            $port = ':' . $this->getPort();
        }

        if (substr($this->path, 0, 1) == '/') {
            $path = $this->normalisePath($this->path);
        }
        $encoded = $this->getEncodedRequest();
        $fragment = $this->getFragment() ? '#' . $this->getFragment() : '';
        $coords = $this->getX() === false ? '' : '?' . $this->getX() . ',' . $this->getY();
        return "$scheme$identity$host$port$path$encoded$fragment$coords";
    }

    /**
     *    Replaces unknown sections to turn a relative
     *    URL into an absolute one. The base URL can
     *    be either a string or a SimpleUrl object.
     *
     * @param string /SimpleUrl $base       Base URL
     */
    public function makeAbsolute($base)
    {
        if (!is_object($base)) {
            $base = new SimpleUrl($base);
        }
        if ($this->getHost()) {
            $scheme = $this->getScheme();
            $host = $this->getHost();
            $port = $this->getPort() ? ':' . $this->getPort() : '';
            $identity = $this->getIdentity() ? $this->getIdentity() . '@' : '';
            if (!$identity) {
                $identity = $base->getIdentity() ? $base->getIdentity() . '@' : '';
            }
        } else {
            $scheme = $base->getScheme();
            $host = $base->getHost();
            $port = $base->getPort() ? ':' . $base->getPort() : '';
            $identity = $base->getIdentity() ? $base->getIdentity() . '@' : '';
        }
        $path = $this->normalisePath($this->extractAbsolutePath($base));
        $encoded = $this->getEncodedRequest();
        $fragment = $this->getFragment() ? '#' . $this->getFragment() : '';
        $coords = $this->getX() === false ? '' : '?' . $this->getX() . ',' . $this->getY();
        return new SimpleUrl("$scheme://$identity$host$port$path$encoded$fragment$coords");
    }

    /**
     *    Replaces unknown sections of the path with base parts
     *    to return a complete absolute one.
     *
     * @param string /SimpleUrl $base       Base URL
     * @param string                       absolute path
     */
    protected function extractAbsolutePath($base)
    {
        if ($this->getHost()) {
            return $this->path;
        }
        if (!$this->isRelativePath($this->path)) {
            return $this->path;
        }
        if ($this->path) {
            return $base->getBasePath() . $this->path;
        }
        return $base->getPath();
    }

    /**
     *    Simple test to see if a path part is relative.
     *
     * @param string $path path to test
     *
     * @return bool true if starts with a "/"
     */
    protected function isRelativePath($path)
    {
        return substr($path, 0, 1) != '/';
    }

    /**
     *    Extracts the username and password for use in rendering
     *    a URL.
     *
     * @return string/boolean    Form of username:password or false
     */
    public function getIdentity()
    {
        if ($this->username && $this->password) {
            return $this->username . ':' . $this->password;
        }
        return false;
    }

    /**
     *    Replaces . and .. sections of the path.
     *
     * @param string $path unoptimised path
     *
     * @return string path with dots removed if possible
     */
    public function normalisePath($path)
    {
        $path = preg_replace('|/\./|', '/', $path);
        return preg_replace('|/[^/]+/\.\./|', '/', $path);
    }

    /**
     *    A pipe seperated list of all TLDs that result in two part
     *    domain names.
     *
     * @return string pipe separated list
     */
    public static function getAllTopLevelDomains()
    {
        return 'com|edu|net|org|gov|mil|int|biz|info|name|pro|aero|coop|museum';
    }
}

<?php

/**
 *  Base include file for SimpleTest
 *
 * @version    $Id$
 */

/**#@+
 *  include other SimpleTest class files
 */
require_once dirname(__FILE__) . '/url.php';
/**#@-*/

/**
 *    Cookie data holder. Cookie rules are full of pretty
 *    arbitary stuff. I have used...
 *    http://wp.netscape.com/newsref/std/cookie_spec.html
 *    http://www.cookiecentral.com/faq/
 */
class SimpleCookie
{
    private $host;
    private $name;
    private $value;
    private $path;
    private $expiry;
    private $is_secure;

    /**
     *    Constructor. Sets the stored values.
     *
     * @param string $name cookie key
     * @param string $value value of cookie
     * @param string $path cookie path if not host wide
     * @param string $expiry expiry date as string
     * @param bool $is_secure currently ignored
     */
    public function __construct($name, $value = false, $path = false, $expiry = false, $is_secure = false)
    {
        $this->host = false;
        $this->name = $name;
        $this->value = $value;
        $this->path = ($path ? $this->fixPath($path) : '/');
        $this->expiry = false;
        if (is_string($expiry)) {
            $this->expiry = strtotime($expiry);
        } elseif (is_integer($expiry)) {
            $this->expiry = $expiry;
        }
        $this->is_secure = $is_secure;
    }

    /**
     *    Sets the host. The cookie rules determine
     *    that the first two parts are taken for
     *    certain TLDs and three for others. If the
     *    new host does not match these rules then the
     *    call will fail.
     *
     * @param string $host new hostname
     *
     * @return bool true if hostname is valid
     */
    public function setHost($host)
    {
        if ($host = $this->truncateHost($host)) {
            $this->host = $host;
            return true;
        }
        return false;
    }

    /**
     *    Accessor for the truncated host to which this
     *    cookie applies.
     *
     * @return string truncated hostname
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     *    Test for a cookie being valid for a host name.
     *
     * @param string $host host to test against
     *
     * @return bool true if the cookie would be valid
     *              here
     */
    public function isValidHost($host)
    {
        return $this->truncateHost($host) === $this->getHost();
    }

    /**
     *    Extracts just the domain part that determines a
     *    cookie's host validity.
     *
     * @param string $host host name to truncate
     *
     * @return string domain or false on a bad host
     */
    protected function truncateHost($host)
    {
        $tlds = SimpleUrl::getAllTopLevelDomains();
        if (preg_match('/[a-z\-]+\.(' . $tlds . ')$/i', $host, $matches)) {
            return $matches[0];
        } elseif (preg_match('/[a-z\-]+\.[a-z\-]+\.[a-z\-]+$/i', $host, $matches)) {
            return $matches[0];
        }
        return false;
    }

    /**
     *    Accessor for name.
     *
     * @return string cookie key
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     *    Accessor for value. A deleted cookie will
     *    have an empty string for this.
     *
     * @return string cookie value
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     *    Accessor for path.
     *
     * @return string valid cookie path
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     *    Tests a path to see if the cookie applies
     *    there. The test path must be longer or
     *    equal to the cookie path.
     *
     * @param string $path path to test against
     *
     * @return bool true if cookie valid here
     */
    public function isValidPath($path)
    {
        return strncmp(
            $this->fixPath($path),
            $this->getPath(),
            strlen($this->getPath())) == 0;
    }

    /**
     *    Accessor for expiry.
     *
     * @return string expiry string
     */
    public function getExpiry()
    {
        if (!$this->expiry) {
            return false;
        }
        return gmdate('D, d M Y H:i:s', $this->expiry) . ' GMT';
    }

    /**
     *    Test to see if cookie is expired against
     *    the cookie format time or timestamp.
     *    Will give true for a session cookie.
     *
     * @param int /string $now  Time to test against. Result
     *                                will be false if this time
     *                                is later than the cookie expiry.
     *                                Can be either a timestamp integer
     *                                or a cookie format date.
     */
    public function isExpired($now)
    {
        if (!$this->expiry) {
            return true;
        }
        if (is_string($now)) {
            $now = strtotime($now);
        }
        return $this->expiry < $now;
    }

    /**
     *    Ages the cookie by the specified number of
     *    seconds.
     *
     * @param int $interval in seconds
     *
     * @public
     */
    public function agePrematurely($interval)
    {
        if ($this->expiry) {
            $this->expiry -= $interval;
        }
    }

    /**
     *    Accessor for the secure flag.
     *
     * @return bool true if cookie needs SSL
     */
    public function isSecure()
    {
        return $this->is_secure;
    }

    /**
     *    Adds a trailing and leading slash to the path
     *    if missing.
     *
     * @param string $path path to fix
     */
    protected function fixPath($path)
    {
        if (substr($path, 0, 1) != '/') {
            $path = '/' . $path;
        }
        if (substr($path, -1, 1) != '/') {
            $path .= '/';
        }
        return $path;
    }
}

/**
 *    Repository for cookies. This stuff is a
 *    tiny bit browser dependent.
 */
class SimpleCookieJar
{
    private $cookies;

    /**
     *    Constructor. Jar starts empty.
     */
    public function __construct()
    {
        $this->cookies = [];
    }

    /**
     *    Removes expired and temporary cookies as if
     *    the browser was closed and re-opened.
     *
     * @param string /integer $now   Time to test expiry against
     */
    public function restartSession($date = false)
    {
        $surviving_cookies = [];
        for ($i = 0; $i < count($this->cookies); $i++) {
            if (!$this->cookies[$i]->getValue()) {
                continue;
            }
            if (!$this->cookies[$i]->getExpiry()) {
                continue;
            }
            if ($date && $this->cookies[$i]->isExpired($date)) {
                continue;
            }
            $surviving_cookies[] = $this->cookies[$i];
        }
        $this->cookies = $surviving_cookies;
    }

    /**
     *    Ages all cookies in the cookie jar.
     *
     * @param int $interval The old session is moved
     *                      into the past by this number
     *                      of seconds. Cookies now over
     *                      age will be removed.
     */
    public function agePrematurely($interval)
    {
        for ($i = 0; $i < count($this->cookies); $i++) {
            $this->cookies[$i]->agePrematurely($interval);
        }
    }

    /**
     *    Sets an additional cookie. If a cookie has
     *    the same name and path it is replaced.
     *
     * @param string $name cookie key
     * @param string $value value of cookie
     * @param string $host host upon which the cookie is valid
     * @param string $path cookie path if not host wide
     * @param string $expiry expiry date
     */
    public function setCookie($name, $value, $host = false, $path = '/', $expiry = false)
    {
        $cookie = new SimpleCookie($name, $value, $path, $expiry);
        if ($host) {
            $cookie->setHost($host);
        }
        $this->cookies[$this->findFirstMatch($cookie)] = $cookie;
    }

    /**
     *    Finds a matching cookie to write over or the
     *    first empty slot if none.
     *
     * @param SimpleCookie $cookie cookie to write into jar
     *
     * @return int available slot
     */
    protected function findFirstMatch($cookie)
    {
        for ($i = 0; $i < count($this->cookies); $i++) {
            $is_match = $this->isMatch(
                $cookie,
                $this->cookies[$i]->getHost(),
                $this->cookies[$i]->getPath(),
                $this->cookies[$i]->getName());
            if ($is_match) {
                return $i;
            }
        }
        return count($this->cookies);
    }

    /**
     *    Reads the most specific cookie value from the
     *    browser cookies. Looks for the longest path that
     *    matches.
     *
     * @param string $host host to search
     * @param string $path applicable path
     * @param string $name name of cookie to read
     *
     * @return string false if not present, else the
     *                value as a string
     */
    public function getCookieValue($host, $path, $name)
    {
        $longest_path = '';
        foreach ($this->cookies as $cookie) {
            if ($this->isMatch($cookie, $host, $path, $name)) {
                if (strlen($cookie->getPath()) > strlen($longest_path)) {
                    $value = $cookie->getValue();
                    $longest_path = $cookie->getPath();
                }
            }
        }
        return $value ?? false;
    }

    /**
     *    Tests cookie for matching against search
     *    criteria.
     *
     * @param SimpleTest $cookie cookie to test
     * @param string $host host must match
     * @param string $path cookie path must be shorter than
     *                     this path
     * @param string $name name must match
     *
     * @return bool true if matched
     */
    protected function isMatch($cookie, $host, $path, $name)
    {
        if ($cookie->getName() != $name) {
            return false;
        }
        if ($host && $cookie->getHost() && !$cookie->isValidHost($host)) {
            return false;
        }
        if (!$cookie->isValidPath($path)) {
            return false;
        }
        return true;
    }

    /**
     *    Uses a URL to sift relevant cookies by host and
     *    path. Results are list of strings of form "name=value".
     *
     * @param SimpleUrl $url url to select by
     *
     * @return array valid name and value pairs
     */
    public function selectAsPairs($url)
    {
        $pairs = [];
        foreach ($this->cookies as $cookie) {
            if ($this->isMatch($cookie, $url->getHost(), $url->getPath(), $cookie->getName())) {
                $pairs[] = $cookie->getName() . '=' . $cookie->getValue();
            }
        }
        return $pairs;
    }
}

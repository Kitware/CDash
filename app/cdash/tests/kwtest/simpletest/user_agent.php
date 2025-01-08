<?php

/**
 *  Base include file for SimpleTest
 *
 * @version    $Id$
 */

/**#@+
 *  include other SimpleTest class files
 */
require_once dirname(__FILE__) . '/cookies.php';
require_once dirname(__FILE__) . '/http.php';
require_once dirname(__FILE__) . '/encoding.php';
require_once dirname(__FILE__) . '/authentication.php';
/**#@-*/

if (!defined('DEFAULT_MAX_REDIRECTS')) {
    define('DEFAULT_MAX_REDIRECTS', 3);
}
if (!defined('DEFAULT_CONNECTION_TIMEOUT')) {
    define('DEFAULT_CONNECTION_TIMEOUT', 15);
}

/**
 *    Fetches web pages whilst keeping track of
 *    cookies and authentication.
 */
class SimpleUserAgent
{
    private $cookie_jar;
    private $cookies_enabled = true;
    private $authenticator;
    private $max_redirects = DEFAULT_MAX_REDIRECTS;
    private $proxy = false;
    private $proxy_username = false;
    private $proxy_password = false;
    private $connection_timeout = DEFAULT_CONNECTION_TIMEOUT;
    private $additional_headers = [];

    /**
     *    Starts with no cookies, realms or proxies.
     */
    public function __construct()
    {
        $this->cookie_jar = new SimpleCookieJar();
        $this->authenticator = new SimpleAuthenticator();
    }

    /**
     *    Removes expired and temporary cookies as if
     *    the browser was closed and re-opened. Authorisation
     *    has to be obtained again as well.
     *
     * @param string /integer $date   Time when session restarted.
     *                                  If omitted then all persistent
     *                                  cookies are kept.
     */
    public function restart($date = false)
    {
        $this->cookie_jar->restartSession($date);
        $this->authenticator->restartSession();
    }

    /**
     *    Adds a header to every fetch.
     *
     * @param string $header header line to add to every
     *                       request until cleared
     */
    public function addHeader($header)
    {
        $this->additional_headers[] = $header;
    }

    /**
     *    Ages the cookies by the specified time.
     *
     * @param int $interval amount in seconds
     */
    public function ageCookies($interval)
    {
        $this->cookie_jar->agePrematurely($interval);
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
        $this->cookie_jar->setCookie($name, $value, $host, $path, $expiry);
    }

    /**
     *    Reads the most specific cookie value from the
     *    browser cookies.
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
        return $this->cookie_jar->getCookieValue($host, $path, $name);
    }

    /**
     *    Reads the current cookies within the base URL.
     *
     * @param string $name key of cookie to find
     * @param SimpleUrl $base base URL to search from
     *
     * @return string/boolean  Null if there is no base URL, false
     *                            if the cookie is not set
     */
    public function getBaseCookieValue($name, $base)
    {
        if (!$base) {
            return;
        }
        return $this->getCookieValue($base->getHost(), $base->getPath(), $name);
    }

    /**
     *    Switches off cookie sending and recieving.
     */
    public function ignoreCookies()
    {
        $this->cookies_enabled = false;
    }

    /**
     *    Switches back on the cookie sending and recieving.
     */
    public function useCookies()
    {
        $this->cookies_enabled = true;
    }

    /**
     *    Sets the socket timeout for opening a connection.
     *
     * @param int $timeout maximum time in seconds
     */
    public function setConnectionTimeout($timeout)
    {
        $this->connection_timeout = $timeout;
    }

    /**
     *    Sets the maximum number of redirects before
     *    a page will be loaded anyway.
     *
     * @param int $max most hops allowed
     */
    public function setMaximumRedirects($max)
    {
        $this->max_redirects = $max;
    }

    /**
     *    Sets proxy to use on all requests for when
     *    testing from behind a firewall. Set URL
     *    to false to disable.
     *
     * @param string $proxy proxy URL
     * @param string $username proxy username for authentication
     * @param string $password proxy password for authentication
     */
    public function useProxy($proxy, $username, $password)
    {
        if (!$proxy) {
            $this->proxy = false;
            return;
        }
        if ((strncmp($proxy, 'http://', 7) != 0) && (strncmp($proxy, 'https://', 8) != 0)) {
            $proxy = 'http://' . $proxy;
        }
        $this->proxy = new SimpleUrl($proxy);
        $this->proxy_username = $username;
        $this->proxy_password = $password;
    }

    /**
     *    Test to see if the redirect limit is passed.
     *
     * @param int $redirects count so far
     *
     * @return bool true if over
     */
    protected function isTooManyRedirects($redirects)
    {
        return $redirects > $this->max_redirects;
    }

    /**
     *    Sets the identity for the current realm.
     *
     * @param string $host host to which realm applies
     * @param string $realm full name of realm
     * @param string $username username for realm
     * @param string $password password for realm
     */
    public function setIdentity($host, $realm, $username, $password)
    {
        $this->authenticator->setIdentityForRealm($host, $realm, $username, $password);
    }

    /**
     *    Fetches a URL as a response object. Will keep trying if redirected.
     *    It will also collect authentication realm information.
     *
     * @param string /SimpleUrl $url      Target to fetch
     * @param SimpleEncoding $encoding additional parameters for request
     *
     * @return SimpleHttpResponse hopefully the target page
     */
    public function fetchResponse($url, $encoding)
    {
        if ($encoding->getMethod() != 'POST') {
            $url->addRequestParameters($encoding);
            $encoding->clear();
        }
        $response = $this->fetchWhileRedirected($url, $encoding);
        if ($headers = $response->getHeaders()) {
            if ($headers->isChallenge()) {
                $this->authenticator->addRealm(
                    $url,
                    $headers->getAuthentication(),
                    $headers->getRealm());
            }
        }
        return $response;
    }

    /**
     *    Fetches the page until no longer redirected or
     *    until the redirect limit runs out.
     *
     * @param SimpleUrl $url target to fetch
     * @param SimpelFormEncoding $encoding additional parameters for request
     *
     * @return SimpleHttpResponse hopefully the target page
     */
    protected function fetchWhileRedirected($url, $encoding)
    {
        $redirects = 0;
        do {
            $response = $this->fetch($url, $encoding);
            if ($response->isError()) {
                return $response;
            }
            $headers = $response->getHeaders();
            if ($this->cookies_enabled) {
                $headers->writeCookiesToJar($this->cookie_jar, $url);
            }
            if (!$headers->isRedirect()) {
                break;
            }
            $location = new SimpleUrl($headers->getLocation());
            $url = $location->makeAbsolute($url);
            $encoding = new SimpleGetEncoding();
        } while (!$this->isTooManyRedirects(++$redirects));
        return $response;
    }

    /**
     *    Actually make the web request.
     *
     * @param SimpleUrl $url target to fetch
     * @param SimpleFormEncoding $encoding additional parameters for request
     *
     * @return SimpleHttpResponse headers and hopefully content
     */
    protected function fetch($url, $encoding)
    {
        $request = $this->createRequest($url, $encoding);
        return $request->fetch($this->connection_timeout);
    }

    /**
     *    Creates a full page request.
     *
     * @param SimpleUrl $url target to fetch as url object
     * @param simpleFormEncoding $encoding POST/GET parameters
     *
     * @return SimpleHttpRequest new request
     */
    protected function createRequest($url, $encoding)
    {
        $request = $this->createHttpRequest($url, $encoding);
        $this->addAdditionalHeaders($request);
        if ($this->cookies_enabled) {
            $request->readCookiesFromJar($this->cookie_jar, $url);
        }
        $this->authenticator->addHeaders($request, $url);
        return $request;
    }

    /**
     *    Builds the appropriate HTTP request object.
     *
     * @param SimpleUrl $url target to fetch as url object
     *
     * @return SimpleHttpRequest new request object
     */
    protected function createHttpRequest($url, $encoding)
    {
        return new SimpleHttpRequest($this->createRoute($url), $encoding);
    }

    /**
     *    Sets up either a direct route or via a proxy.
     *
     * @param SimpleUrl $url target to fetch as url object
     *
     * @return SimpleRoute route to take to fetch URL
     */
    protected function createRoute($url)
    {
        if ($this->proxy) {
            return new SimpleProxyRoute(
                $url,
                $this->proxy,
                $this->proxy_username,
                $this->proxy_password);
        }
        return new SimpleRoute($url);
    }

    /**
     *    Adds additional manual headers.
     *
     * @param SimpleHttpRequest $request outgoing request
     */
    protected function addAdditionalHeaders(&$request)
    {
        foreach ($this->additional_headers as $header) {
            $request->addHeaderLine($header);
        }
    }
}

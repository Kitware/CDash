<?php
namespace CDash;

use CDash\Singleton;

class Config extends Singleton
{
    private $_config;

    protected function __construct()
    {
        include 'config/config.php';
        $this->_config = get_defined_vars();
    }

    public function get($name)
    {
        if (isset($this->_config[$name])) {
            return $this->_config[$name];
        }
    }

    public function set($name, $value)
    {
        $this->_config[$name] = $value;
    }

    /**
     * @return string
     */
    public function getServer()
    {
        $server = $this->get('CDASH_SERVER_NAME');
        if (empty($server)) {
            $server = $_SERVER['SERVER_NAME'];
        }
        return $server;
    }

    /**
     * @return string
     */
    public function getProtocol()
    {
        $protocol = 'http';
        if ($this->get('CDASH_USE_HTTPS') ||
            (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) {
            $protocol = 'https';
        }
        return $protocol;
    }

    /**
     * @return string
     */
    public function getServerPort()
    {
        if (isset($_SERVER['SERVER_PORT'])
            && $_SERVER['SERVER_PORT'] != 80
            && $_SERVER['SERVER_PORT'] != 443) {
            return $_SERVER['SERVER_PORT'];
        }
    }

    /**
     * @return string
     */
    public function getBaseUrl()
    {
        $uri = $this->get('CDASH_BASE_URL');

        if (!$uri) {
            $protocol = $this->getProtocol();
            $server = $this->getServer();
            $port = $this->getServerPort();
            $uri = "{$protocol}://{$server}";

            if ($port) {
                $uri = "{$uri}:{$port}";
            }

            $uri = "{$uri}{$_SERVER['REQUEST_URI']}";
        }
        return $uri;
    }

    public function load($config)
    {
        return include "config/{$config}.php";
    }
}

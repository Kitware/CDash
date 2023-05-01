<?php
namespace CDash;

class Config extends Singleton
{
    private $_config;

    protected function __construct()
    {
        include 'config/config.php';
        include 'include/version.php';
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

    public static function getVersion(): string
    {
        return file_get_contents(public_path('VERSION'));
    }

    public function getServer(): string
    {
        $server = $this->get('CDASH_SERVER_NAME');
        if (empty($server)) {
            if (isset($_SERVER['SERVER_NAME'])) {
                $server = $_SERVER['SERVER_NAME'];
            } else {
                $server = 'localhost';
            }
        }
        return $server;
    }

    public function getProtocol(): string
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

    public function getPath(): string
    {
        $path = config('cdash.curl_localhost_prefix') ?: $_SERVER['REQUEST_URI'];
        if (!str_starts_with($path, '/')) {
            $path = "/{$path}";
        }
        return $path;
    }

    public function getBaseUrl(): string
    {
        $uri = config('app.url');

        if (!$uri) {
            $protocol = $this->getProtocol();
            $host = $this->getServer();
            $port = $this->getServerPort() ? ":{$this->getServerPort()}" : '';
            $path = $this->getPath();

            // Trim any known subdirectories off of the path.
            $subdirs = ['/ajax/', '/api/', '/auth/'];
            foreach ($subdirs as $subdir) {
                $pos = strpos($path, $subdir);
                if ($pos !== false) {
                    $path = substr($path, 0, $pos);
                }
            }

            // Also trim any .php files from the path.
            if (strpos($path, '.php') !== false) {
                $path = dirname($path);
            }

            $uri = "{$protocol}://{$host}{$port}{$path}";
        }

        return rtrim($uri, '/');
    }
}

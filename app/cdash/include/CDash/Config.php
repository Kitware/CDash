<?php
namespace CDash;

class Config extends Singleton
{
    private array $_config;

    protected function __construct()
    {
        include 'config/config.php';
        include 'include/version.php';
        $this->_config = get_defined_vars();
    }

    /**
     * @deprecated 09/04/2023  Use config() instead.
     */
    public function get($name)
    {
        if (isset($this->_config[$name])) {
            return $this->_config[$name];
        }
    }

    /**
     * @deprecated 09/04/2023  Use config() instead.
     */
    public function set($name, $value)
    {
        $this->_config[$name] = $value;
    }

    /**
     * @deprecated 09/04/2023  Use url() instead.
     */
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

    /**
     * @deprecated 09/04/2023  Use url() instead.
     */
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
     * @deprecated 09/04/2023  Use url() instead.
     */
    public function getServerPort(): ?string
    {
        if (isset($_SERVER['SERVER_PORT'])
            && $_SERVER['SERVER_PORT'] != 80
            && $_SERVER['SERVER_PORT'] != 443) {
            return $_SERVER['SERVER_PORT'];
        }
        return null;
    }

    private function getPath(): string
    {
        $path = config('cdash.curl_localhost_prefix') ?: $_SERVER['REQUEST_URI'];
        if (!str_starts_with($path, '/')) {
            $path = "/{$path}";
        }
        return $path;
    }

    /**
     * @deprecated 09/04/2023  Use url() instead.
     */
    public function getBaseUrl(): string
    {
        $uri = config('app.url');

        if (!$uri) {
            $protocol = $this->getProtocol();
            $host = $this->getServer();
            $port = $this->getServerPort() !== null ? ":{$this->getServerPort()}" : '';
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
            if (str_contains($path, '.php')) {
                $path = dirname($path);
            }

            $uri = "{$protocol}://{$host}{$port}{$path}";
        }

        return rtrim($uri, '/');
    }
}

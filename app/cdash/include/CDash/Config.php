<?php
namespace CDash;

class Config extends Singleton
{
    private array $_config;

    protected function __construct()
    {
        include 'config/config.php';
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
}

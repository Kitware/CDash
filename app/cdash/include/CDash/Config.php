<?php
namespace CDash;

class Config extends Singleton
{
    private array $_config;

    protected function __construct()
    {
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
}

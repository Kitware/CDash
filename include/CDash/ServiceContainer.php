<?php
namespace CDash;

use DI\ContainerBuilder;
use DI\Container;

class ServiceContainer extends Singleton
{
    private $container;

    protected function __construct()
    {
        $config = Config::getInstance();
        $definitions = "{$config->get('CDASH_ROOT_DIR')}/config/di.php";
        $builder = new ContainerBuilder();
        $builder->addDefinitions($definitions);
        $this->container = $builder->build();
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function create($class_name)
    {
        return $this->container->get($class_name);
    }

    public function setContainer(Container $container)
    {
        $this->container = $container;
    }
}

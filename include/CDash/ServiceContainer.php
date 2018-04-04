<?php
/*=========================================================================
  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) Kitware, Inc. All rights reserved.
  See LICENSE or http://www.cdash.org/licensing/ for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE. See the above copyright notices for more information.
=========================================================================*/
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

    /**
     * @return Container
     */
    public static function container()
    {
        return self::getInstance()->getContainer();
    }

    /**
     * The create method will return a new instance of a class.
     *
     * @param $class_name
     * @return mixed
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function create($class_name)
    {
        return $this->container->make($class_name);
    }

    /**
     * The get method will return a singelton instance of a class.
     *
     * @param $class_name
     * @return mixed
     * @throws \DI\DependencyException
     * @throws \DI\NotFoundException
     */
    public function get($class_name)
    {
        return $this->container->get($class_name);
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function setContainer(Container $container)
    {
        $this->container = $container;
    }
}

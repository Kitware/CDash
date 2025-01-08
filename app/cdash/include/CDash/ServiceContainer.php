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

use DI\Container;
use DI\ContainerBuilder;

class ServiceContainer extends Singleton
{
    private $container;

    protected function __construct()
    {
        $builder = new ContainerBuilder();
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
     */
    public function create($class_name)
    {
        return $this->container->make($class_name);
    }

    /**
     * The get method will return a singelton instance of a class.
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

    public static function singleton($class_name)
    {
        /** @var ServiceContainer $self */
        $self = self::getInstance();
        return $self->get($class_name);
    }

    public static function instance($class_name)
    {
        $self = self::getInstance();
        return $self->create($class_name);
    }
}

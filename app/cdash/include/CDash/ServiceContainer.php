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
    private Container $container;

    protected function __construct()
    {
        $builder = new ContainerBuilder();
        $this->container = $builder->build();
    }

    public static function container(): Container
    {
        return self::getInstance()->getContainer();
    }

    /**
     * The create method will return a new instance of a class.
     *
     * @template T
     *
     * @param class-string<T> $class_name
     *
     * @return T
     */
    public function create(string $class_name)
    {
        return $this->container->make($class_name);
    }

    /**
     * The get method will return a singleton instance of a class.
     *
     * @template T
     *
     * @param class-string<T> $class_name
     *
     * @return T
     */
    public function get(string $class_name)
    {
        return $this->container->get($class_name);
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }
}

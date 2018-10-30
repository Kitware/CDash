<?php
/**
 * =========================================================================
 *   Program:   CDash - Cross-Platform Dashboard System
 *   Module:    $Id$
 *   Language:  PHP
 *   Date:      $Date$
 *   Version:   $Revision$
 *   Copyright (c) Kitware, Inc. All rights reserved.
 *   See LICENSE or http://www.cdash.org/licensing/ for details.
 *   This software is distributed WITHOUT ANY WARRANTY; without even
 *   the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
 *   PURPOSE. See the above copyright notices for more information.
 * =========================================================================
 */

namespace CDash\Lib;

trait ServiceContainer
{
    protected $serviceContainer;

    /**
     * @return \CDash\ServiceContainer
     */
    public function getServiceContainer()
    {
        if (!$this->serviceContainer) {
            $this->serviceContainer = \CDash\ServiceContainer::getInstance();
        }
        return $this->serviceContainer;
    }

    public function getInstance($class)
    {
        $container = $this->getServiceContainer();
        return $container->create($class);
    }

    public function getSingleton($class)
    {
        $container = $this->getServiceContainer();
        return $container->get($class);
    }
}

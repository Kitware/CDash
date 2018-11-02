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

use CDash\Config;

/**
 * Trait Configuration
 * @package CDash\Lib
 */
trait Configuration
{
    /** @var Config $config */
    protected $config;

    /**
     * @return Config
     */
    protected function getConfig()
    {
        if (!$this->config) {
            $this->config = Config::getInstance();
        }
        return $this->config;
    }

    /**
     * @param $name
     * @return mixed
     */
    protected function getConfigValue($name)
    {
        $config = $this->getConfig();
        return $config->get($name);
    }

    /**
     * @param $name
     * @param $value
     * @return void
     */
    protected function setConfigValue($name, $value)
    {
        $config = $this->getConfig();
        $config->set($name, $value);
    }
}

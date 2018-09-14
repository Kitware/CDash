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
namespace CDash\Collection;

class CallableCollection extends Collection
{
    public function add(callable $item)
    {
        // what if the callable is anonymous?
        $key = null;
        if (is_array($item)) {
            list($object, $method) = $item;
            $className = get_class($object);
            $key = "{$className}::{$method}";
        } elseif (is_string($item)) {
            $key = $item;
        }

        $this->addItem($item, $key);
    }
}

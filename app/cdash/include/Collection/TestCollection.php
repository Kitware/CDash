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

use App\Models\BuildTest;

class TestCollection extends Collection
{

    /**
     * @param BuildTest $buildtest
     * @return $this
     */
    public function add(BuildTest $buildtest)
    {
        parent::addItem($buildtest, $buildtest->test->name);
        return $this;
    }
}

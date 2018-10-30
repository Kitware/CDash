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

namespace CDash\Lib\Collection;

use CDash\Model\TestMeasurement;

class TestMeasurementCollection extends Collection
{
    /**
     * @param TestMeasurement $measurement
     */
    public function add(TestMeasurement $measurement)
    {
        $key = str_replace(' ', '', $measurement->Name);
        parent::addItem($measurement, $key);
    }
}

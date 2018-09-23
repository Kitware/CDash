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

namespace CDash\Messaging\Topic;


use CDash\Collection\LabelCollection;
use CDash\Model\Build;

interface Labelable
{
    /**
     * @param Build $build
     * @return LabelCollection
     */
    public function getLabelsFromBuild(Build $build);

    /**
     * @param Build $build
     * @param LabelCollection $labels
     * @return void
     */
    public function setTopicDataWithLabels(Build $build, LabelCollection $labels);
}

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

namespace CDash\Lib\Parsing;

use CDash\Lib\Collection\BuildCollection;

/**
 * Interface SubmissionParserInterface
 * @package CDash\Lib\Parsing
 */
interface SubmissionParserInterface
{
    /**
     * @return string
     */
    public function getSiteName();

    /**
     * @return string|int
     */
    public function getSiteId();

    /**
     * @return string
     */
    public function getBuildStamp();

    /**
     * @return string
     */
    public function getBuildName();

    /**
     * @return \CDash\Model\Build[]
     */
    public function getBuilds();

    /**
     * @return BuildCollection
     */
    public function getBuildCollection();

    /**
     * @param $projectId
     * @return self
     */
    public function setProjectId($projectId);

    /**
     * @param $scheduleId
     * @return self
     */
    public function setScheduleId($scheduleId);

}

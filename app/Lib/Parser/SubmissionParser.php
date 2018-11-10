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

namespace CDash\Lib\Parser;

use CDash\Lib\Collection\BuildCollection;
use CDash\Lib\Configuration;
use CDash\Lib\ServiceContainer;
use CDash\Model\Build;
use CDash\Model\Site;

trait SubmissionParser
{
    use ServiceContainer;
    use Configuration;

    /** @var Build $build */
    protected $build;

    /** @var string|int $projectId */
    protected $projectId;

    /** @var string|int $scheduleId */
    protected $scheduleId;

    /** @var Site */
    protected $site;

    /** @var string $subProjectName */
    protected $subProjectName;

    /** @var string $startTimeStamp */
    protected $startTimeStamp;

    /** @var string $endTimeStamp */
    protected $endTimeStamp;

    /**
     * @return string
     */
    public function getSiteName()
    {
        if ($this->site) {
            return $this->site->Name;
        }
    }

    /**
     * @return string|int
     */
    public function getSiteId()
    {
        if ($this->site) {
            return $this->site->Id;
        }
    }

    /**
     * Returns either the parent build given multiple builds in the submission or the sole submitted build
     *
     * @return Build
     */
    public function getBuild()
    {
        $build = null;
        $builds = $this->getBuilds();
        if (count($builds) > 1) {
            $build = new Build();
            $build->Id = $builds[0]->GetParentId();
        } else {
            $build = $builds[0];
        }
        return $build;
    }

    /**
     * @return string
     */
    public function getBuildStamp()
    {
        $stamp = '';
        $build = $this->getBuild();
        if ($build) {
            $stamp = $build->GetStamp();
        }
        return $stamp;
    }

    /**
     * @return string
     */
    public function getBuildName()
    {
        $name = '';
        $build = $this->getBuild();
        if ($build) {
            $name = $build->Name;
        }
        return $name;
    }

    /**
     * @return \CDash\Model\Build[]
     */
    public function getBuilds()
    {
        $builds = [];
        if ($this->build) {
            $builds[] = $this->build;
        }
        return $builds;
    }

    /**
     * @return BuildCollection
     */
    public function getBuildCollection()
    {
        $collection = $this->getInstance(BuildCollection::class);
        foreach ($this->getBuilds() as $build) {
            $collection->add($build);
        }
        return $collection;
    }

    /**
     * @param $projectId
     * @return self
     */
    public function setProjectId($projectId)
    {
        $this->projectId = $projectId;
        return $this;
    }

    /**
     * @param $scheduleId
     * @return self
     */
    public function setScheduleId($scheduleId)
    {
        $this->scheduleId = $scheduleId;
        return $this;
    }

    /**
     * @deprecated Use getBuildCollection instead
     * @return BuildCollection
     */
    public function getActionableBuilds()
    {
        return $this->getBuildCollection();
    }
}

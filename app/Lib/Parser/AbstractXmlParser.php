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

use CDash\Lib\Parser\SubmissionParserInterface;
use CDash\Lib\ServiceContainer;
use CDash\Model\Build;
use CDash\Model\Site;

abstract class AbstractXmlParser implements SaxInterface, SubmissionParserInterface
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

    /** @var Stack $stack */
    protected $stack;

    /** @var string $subProjectName */
    protected $subProjectName;

    /** @var string $startTimeStamp */
    protected $startTimeStamp;

    /** @var string $endTimeStamp */
    protected $endTimeStamp;

    /**
     * AbstractXmlParser constructor.
     * @param $projectId
     */
    public function __construct($projectId)
    {
        $this->projectId = $projectId;
        $this->stack = new Stack();
    }

    public function setStack(StackInterface $stack)
    {
        $this->stack = $stack;
        return $this;
    }

    /**
     * @return bool|mixed
     */
    protected function getParent()
    {
        return $this->stack->at($this->stack->size() - 2);
    }

    /**
     * @return mixed
     */
    protected function getElement()
    {
        return $this->stack->top();
    }

    /**
     * @param $parser
     * @param $name
     * @param $attributes
     * @return mixed
     */
    public function startElement($parser, $name, $attributes)
    {
        $this->stack->push($name);

        if ($name == 'SUBPROJECT') {
            $this->subProjectName = $attributes['NAME'];
        }
    }

    /**
     * @param $parser
     * @param $name
     * @return mixed
     */
    public function endElement($parser, $name)
    {
        $this->stack->pop();
    }

    /**
     * @param $parser
     * @param $target
     * @param $data
     * @return mixed
     */
    public function processingInstruction($parser, $target, $data)
    {
        // not implemented
    }

    /**
     * @param $parser
     * @param $open_entity_name
     * @param $base
     * @param $system_id
     * @param $public_id
     * @return mixed
     */
    public function externalEntity($parser, $open_entity_name, $base, $system_id, $public_id)
    {
        // not implemented
    }

    /**
     * @param $parser
     * @param $open_entity_name
     * @param $base
     * @param $system_id
     * @param $public_id
     * @return mixed
     */
    public function skippedEntity($parser, $open_entity_name, $base, $system_id, $public_id)
    {
        // not implemented
    }

    /**
     * @param $parser
     * @param $user_data
     * @param $prefix
     * @param $uri
     * @return mixed
     */
    public function startPrefixMapping($parser, $user_data, $prefix, $uri)
    {
        // not implemented
    }

    /**
     * @param $parser
     * @param $user_data
     * @param $prefix
     * @return mixed
     */
    public function endPrefixMapping($parser, $user_data, $prefix)
    {
        // not implemented
    }

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
     * @return string
     */
    public function getBuildStamp()
    {
        if ($this->build) {
            return $this->build->GetStamp();
        }
    }

    /**
     * @return string
     */
    public function getBuildName()
    {
        if ($this->build) {
            return $this->build->Name;
        }
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

    public function getActionableBuilds()
    {
        return $this->getBuildCollection();
    }
}

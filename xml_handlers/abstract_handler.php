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

require_once 'include/ctestparserutils.php';
require_once 'xml_handlers/CDashSubmissionHandlerInterface.php';
require_once 'xml_handlers/sax_handler.php';
require_once 'xml_handlers/stack.php';

use CDash\Config;
use CDash\Model\Build;
use CDash\Model\Site;
use CDash\Model\Project;

abstract class AbstractHandler implements SaxHandler, CDashSubmissionHandlerInterface
{
    protected $stack;
    protected $projectid;
    protected $scheduleid;
    /** @var  Build $Build */
    protected $Build;
    /** @var  Site $Site */
    protected $Site;
    protected $SubProjectName;

    protected $ModelFactory;
    protected $Project;
    protected $conifg;

    public function __construct($projectid, $scheduleid)
    {
        $this->projectid = $projectid;
        $this->scheduleid = $scheduleid;
        $this->stack = new Stack();
        $this->config = Config::getInstance();
    }

    protected function getParent()
    {
        return $this->stack->at($this->stack->size() - 2);
    }

    protected function getElement()
    {
        return $this->stack->top();
    }

    public function startElement($parser, $name, $attributes)
    {
        $this->stack->push($name);

        if ($name == 'SUBPROJECT') {
            $this->SubProjectName = $attributes['NAME'];
        }
    }

    public function endElement($parser, $name)
    {
        $this->stack->pop();
    }

    public function processingInstruction($parser, $target, $data)
    {
    }

    public function externalEntity($parser, $open_entity_name, $base, $system_id, $public_id)
    {
    }

    public function skippedEntity($parser, $open_entity_name, $base, $system_id, $public_id)
    {
    }

    public function startPrefixMapping($parser, $user_data, $prefix, $uri)
    {
    }

    public function endPrefixMapping($parser, $user_data, $prefix)
    {
    }

    public function getSiteName()
    {
        return $this->Site->Name;
    }

    public function getSiteId()
    {
        return $this->Site->Id;
    }

    public function getBuildStamp()
    {
        return $this->Build->GetStamp();
    }

    public function getBuildName()
    {
        return $this->Build->Name;
    }

    public function getBuilds()
    {
        return [$this->Build];
    }

    /**
     * @return \CDash\ServiceContainer
     */
    protected function getModelFactory()
    {
        if (!$this->ModelFactory) {
            $this->ModelFactory = \CDash\ServiceContainer::getInstance();
        }
        return $this->ModelFactory;
    }

    public function GetProject()
    {
        if (!$this->Project) {
            $factory = $this->getModelFactory();
            $this->Project = $factory->create(Project::class);
            $this->Project->Id = $this->projectid;
        }
        return $this->Project;
    }

    public function GetSite()
    {
        return $this->Site;
    }

    public function GetCommitAuthors()
    {
        return [];
    }
}

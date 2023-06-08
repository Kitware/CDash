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
use CDash\Model\Project;
use App\Models\Site;

abstract class AbstractHandler implements SaxHandler, CDashSubmissionHandlerInterface
{
    protected $stack;
    protected $projectid;
    protected $Append;
    /** @var  Build $Build */
    protected $Build;
    /** @var  Site $Site */
    protected $Site;
    protected $SubProjectName;

    protected $ModelFactory;
    protected $Project;
    protected $conifg;

    public function __construct($projectid)
    {
        $this->projectid = $projectid;
        $this->Append = false;
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

        if (array_key_exists('APPEND', $attributes) && strtolower($attributes['APPEND']) == 'true') {
            $this->Append = true;
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
        return $this->Site->name;
    }

    public function getSiteId()
    {
        return $this->Site->id;
    }

    public function getBuildStamp()
    {
        return $this->Build->GetStamp();
    }

    public function getBuildName()
    {
        return $this->Build->Name;
    }

    public function getSubProjectName()
    {
        return $this->Build->SubProjectName;
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

    /**
     * @return Site
     */
    public function GetSite()
    {
        return $this->Site;
    }
}

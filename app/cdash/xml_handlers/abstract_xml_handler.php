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

use App\Models\Site;
use App\Utils\Stack;
use CDash\Model\Build;
use CDash\Model\Project;

abstract class AbstractXmlHandler extends AbstractSubmissionHandler
{
    private Stack $stack;
    protected bool $Append = false;
    protected Site $Site;
    protected $SubProjectName;

    private $ModelFactory;

    public function __construct(Build|Project $init)
    {
        parent::__construct($init);

        $this->stack = new Stack();
    }

    protected function getParent()
    {
        return $this->stack->at($this->stack->size() - 2);
    }

    protected function getElement()
    {
        return $this->stack->top();
    }

    public function startElement($parser, $name, $attributes): void
    {
        $this->stack->push($name);

        if ($name == 'SUBPROJECT') {
            $this->SubProjectName = $attributes['NAME'];
        }

        if (array_key_exists('APPEND', $attributes) && strtolower($attributes['APPEND']) == 'true') {
            $this->Append = true;
        }
    }

    public function endElement($parser, $name): void
    {
        $this->stack->pop();
    }

    abstract public function text($parser, $data);

    public function getSiteName(): string
    {
        return $this->Site->name;
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

    protected function getModelFactory(): \CDash\ServiceContainer
    {
        if (!$this->ModelFactory) {
            $this->ModelFactory = \CDash\ServiceContainer::getInstance();
        }
        return $this->ModelFactory;
    }

    public function GetProject(): Project
    {
        $this->Project->Fill();
        return $this->Project;
    }

    public function GetSite(): Site
    {
        return $this->Site;
    }
}

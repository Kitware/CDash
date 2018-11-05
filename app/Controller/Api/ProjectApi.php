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
namespace CDash\Controller\Api;

use CDash\Database;
use CDash\Model\Project;

/**
 * Parent class for all API controllers that deal with a particular project.
 **/
class ProjectApi extends \CDash\Controller\Api
{
    protected $project;

    public function __construct(Database $db, Project $project)
    {
        parent::__construct($db);
        $this->project = $project;
    }
}

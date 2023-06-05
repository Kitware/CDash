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
use CDash\Model\Build;
use CDash\Model\Project;

/**
 * Parent class for all API controllers responsible for displaying
 * information about a particular build.
 **/
abstract class BuildApi extends ResultsApi
{
    protected $project;

    public function __construct(Database $db, Build $build)
    {
        $this->build = $build;
        parent::__construct($db, $build->GetProject());
    }
}

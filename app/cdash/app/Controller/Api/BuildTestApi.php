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

use App\Models\BuildTest;
use App\Models\Test;

use CDash\Database;
use CDash\Model\Build;

/**
 * Parent class for all API controllers responsible for displaying
 * information about a particular test run.
 **/
class BuildTestApi extends BuildApi
{
    public $buildtest;
    public $test;

    public function __construct(Database $db, BuildTest $buildtest)
    {
        $this->buildtest = $buildtest;
        $this->test = Test::where('id', '=', $this->buildtest->testid)->first();

        $build = new Build();
        $build->Id = $this->buildtest->buildid;
        $build->FillFromId($build->Id);

        parent::__construct($db, $build);
        $this->project->Fill();
    }
}

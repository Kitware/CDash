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
abstract class BuildTestApi extends BuildApi
{
    public $buildtest;
    public $test;
    public $testHistoryQuery;
    public $testHistoryQueryExtraColumns;
    public $testHistoryQueryExtraJoins;
    public $testHistoryQueryExtraWheres;
    public $testHistoryQueryLimit;
    public $testHistoryQueryOrder;
    public $testHistoryQueryParams;

    public function __construct(Database $db, BuildTest $buildtest)
    {
        $this->buildtest = $buildtest;
        $this->test = Test::where('id', '=', $this->buildtest->testid)->first();

        $build = new Build();
        $build->Id = $this->buildtest->buildid;
        $build->FillFromId($build->Id);

        $this->testHistoryQuery = '';
        $this->testHistoryQueryExtraColumns = '';
        $this->testHistoryQueryExtraJoins = '';
        $this->testHistoryQueryExtraWheres = '';
        $this->testHistoryQueryLimit = '';
        $this->testHistoryQueryOrder = '';

        parent::__construct($db, $build);
        $this->project->Fill();

        $this->testHistoryQueryParams = [
            ':siteid'=> $this->build->SiteId,
            ':projectid' => $this->project->Id,
            ':type' => $this->build->Type,
            ':buildname' => $this->build->Name,
            ':testname' => $this->test->name,
        ];
    }

    public function generateTestHistoryQuery()
    {
        $this->testHistoryQuery =
            "SELECT b.starttime, b2t.id AS buildtestid $this->testHistoryQueryExtraColumns
            FROM build b
            JOIN build2test b2t ON (b.id = b2t.buildid)
            $this->testHistoryQueryExtraJoins
            WHERE b.siteid = :siteid
            AND b.projectid = :projectid
            AND b.type = :type
            AND b.name = :buildname
            AND b2t.testid IN (SELECT id FROM test WHERE name = :testname)
            $this->testHistoryQueryExtraWheres
            ORDER BY b.starttime $this->testHistoryQueryOrder
            $this->testHistoryQueryLimit";
    }
}

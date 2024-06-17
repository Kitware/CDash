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


use Illuminate\Support\Facades\DB;

require_once dirname(__FILE__) . '/cdash_test_case.php';



class DynamicAnalysisSummaryTestCase extends KWWebTestCase
{
    protected $DataDir;
    protected $ParentId;
    protected $StandaloneBuildId;
    protected $ChildIds;

    public function __construct()
    {
        parent::__construct();
        $this->deleteLog($this->logfilename);
        $this->DataDir = dirname(__FILE__) . '/data/DynamicAnalysisSummary';
        $this->ParentId = 0;
        $this->StandaloneBuildId = 0;
        $this->ChildIds = [];
    }

    public function testDynamicAnalysisSummary()
    {
        // Submit our testing file.  This should have already been submitted
        // by an earlier test, but it doesn't hurt to send it up a second time.
        $file = dirname(__FILE__) . '/data/InsightExperimentalExample/Insight_Experimental_DynamicAnalysis.xml';
        if (!$this->submission('InsightExample', $file)) {
            $this->fail("Failed to submit $file");
            return 1;
        }
        $this->VerifyStandaloneBuild();
        $this->pass('Test passed');
    }

    public function testSubProjectDynamicAnalysisSummary()
    {
        // Submit our testing files.
        for ($i = 1; $i < 4; $i++) {
            $file = dirname(__FILE__) . "/data/SubProjectDynamicAnalysis/DynamicAnalysis_$i.xml";
            if (!$this->submission('CrossSubProjectExample', $file)) {
                $this->fail("Failed to submit $file");
                return 1;
            }
        }
        $this->VerifySubProjectBuild();
        $this->pass('Test passed');
    }

    public function testDynamicAnalysisSummaryGetsDeleted()
    {
        // Remove the builds we just created.
        remove_build($this->ParentId);

        // Verify that the dynamicanalysisssummary rows got deleted.
        $result = DB::select("
                SELECT b.id, b.parentid, das.numdefects FROM build AS b
                INNER JOIN dynamicanalysissummary AS das ON (das.buildid=b.id)
                WHERE b.name = 'cross_subproject_DA_example'");
        $num_builds = count($result);
        if ($num_builds !== 0) {
            $this->fail("Expected 0 builds after deletion, found $num_builds");
        }
        $this->pass('Test passed');
    }

    public function VerifyStandaloneBuild()
    {
        // Verify the expected number of defects.
        // Get the ID of this build.
        $row = DB::select("
                SELECT b.id, das.numdefects FROM build AS b
                INNER JOIN dynamicanalysissummary AS das ON (das.buildid=b.id)
                WHERE name = 'Linux-g++-4.1-LesionSizingSandbox_Debug'")[0];
        $this->StandaloneBuildId = $row->id;
        $numdefects = $row->numdefects;
        if ($numdefects != 225) {
            $this->fail("Expected 225, found $numdefects\n");
        }
    }

    public function VerifySubProjectBuild()
    {
        $result = DB::select("
                SELECT b.id, b.parentid, das.numdefects FROM build AS b
                INNER JOIN dynamicanalysissummary AS das ON (das.buildid=b.id)
                WHERE b.name = 'cross_subproject_DA_example'");
        $num_builds = count($result);
        if ($num_builds !== 4) {
            $this->fail("Expected 4 builds, found $num_builds");
        }

        $this->ParentId = 0;
        foreach ($result as $row) {
            $numdefects = (int) $row->numdefects;
            if ((int) $row->parentid === -1) {
                // Parent case.
                $this->ParentId = $row->id;
                if ($numdefects !== 3) {
                    $this->fail("Expected 3 defects for parent, found $numdefects");
                }
            } else {
                // Child case.
                $this->ChildIds[] = $row->id;
                if ($numdefects !== 1) {
                    $this->fail("Expected 1 defect for child, found $numdefects");
                }
            }
        }
    }
}

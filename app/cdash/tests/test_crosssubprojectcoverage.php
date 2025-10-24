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

class CoverageAcrossSubProjectsTestCase extends KWWebTestCase
{
    protected $DataDir;

    public function __construct()
    {
        parent::__construct();
        $this->deleteLog($this->logfilename);
        $this->DataDir = dirname(__FILE__) . '/data/CoverageAcrossSubProjects';
    }

    public function testCreateProjectTest(): void
    {
        // Create a new project for this test.
        $settings = [
            'Name' => 'CrossSubProjectExample',
            'Description' => 'Example of coverage across SubProjects'];
        $this->createProject($settings);
    }

    public function testCreateSubProjects(): void
    {
        $file = "$this->DataDir/Project.xml";
        if (!$this->submission('CrossSubProjectExample', $file)) {
            $this->fail("Failed to submit $file");
            return;
        }
        $this->pass('Test passed');
    }

    public function testSubmitGcovTarCoverage(): void
    {
        $this->deleteLog($this->logfilename);
        $success = true;
        $success &= $this->submitThirdParty();
        $success &= $this->submitExperimental();
        $success &= $this->submitProduction();
        $success &= $this->verifyResults();
        $success &= $this->verifyAggregate();
        if ($success) {
            $this->pass('Test passed');
        }
    }

    public function submitThirdParty()
    {
        return $this->submitResults('MyThirdPartyDependency', '1455044903',
            '1455044904', '121eb59d5dd9c1bf78c2a5e15eb669d0');
    }

    public function submitExperimental()
    {
        return $this->submitResults('MyExperimentalFeature', '1455044906',
            '1455044907', '6adbe63add1bc5171e9e2b9c6a4155de');
    }

    public function submitProduction()
    {
        return $this->submitResults('MyProductionCode', '1455044909',
            '1455044909', '211f7e369cd2bfc983eaba847c1cab65');
    }

    public function submitResults($subproject, $starttime, $endtime, $md5)
    {
        $file = "$this->DataDir/$subproject/Build.xml";
        if (!$this->submission('CrossSubProjectExample', $file)) {
            $this->fail("Failed to submit $file");
            return false;
        }

        // Do the POST submission to get a pending buildid from CDash.
        $post_data = [
            'project' => 'CrossSubProjectExample',
            'subproject' => $subproject,
            'build' => 'subproject_coverage_example',
            'site' => 'localhost',
            'stamp' => '20160209-1908-Nightly',
            'starttime' => $starttime,
            'endtime' => $endtime,
            'track' => 'Nightly',
            'type' => 'GcovTar',
            'datafilesmd5[0]=' => $md5];
        $post_result = $this->post($this->url . '/submit.php', $post_data);
        $post_json = json_decode($post_result, true);
        if ($post_json['status'] != 0) {
            $this->fail(
                'POST returned ' . $post_json['status'] . ":\n" .
                $post_json['description'] . "\n");
            return false;
        }

        $buildid = $post_json['buildid'];
        if (!is_numeric($buildid) || $buildid < 1) {
            $this->fail(
                "Expected positive integer for buildid, instead got $buildid");
            return false;
        }

        // Do the PUT submission to actually upload our data.
        $puturl = $this->url . "/submit.php?type=GcovTar&md5=$md5&filename=gcov.tar&buildid=$buildid";
        $filename = "$this->DataDir/$subproject/gcov.tar";

        $put_result = $this->uploadfile($puturl, $filename);
        if (!str_contains($put_result, '{"status":0}')) {
            $this->fail(
                "status:0 not found in PUT results:\n$put_result\n");
            return 1;
        }
    }

    public function verifyResults()
    {
        $success = true;

        // Verify parent results.
        $this->get($this->url . '/api/v1/index.php?project=CrossSubProjectExample&date=2016-02-09');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $num_parent_coverages = count($jsonobj['coverages']);
        if ($num_parent_coverages != 1) {
            $this->fail("Expected one parent coverage, found $num_parent_coverages");
            return false;
        }
        $coverage = array_pop($jsonobj['coverages']);
        $success &= $this->checkCoverage($coverage, 25, 10, 'parent');

        $parentid = $coverage['buildid'];
        if (empty($parentid) || $parentid < 1) {
            $this->fail('No parentid found when expected');
            return false;
        }

        // Verify child results.
        $this->get($this->url . "/api/v1/index.php?project=CrossSubProjectExample&parentid=$parentid");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $num_groups = count($jsonobj['coveragegroups']);
        if ($num_groups != 4) {
            $this->fail("Expected 4 coverage groups, found $num_groups");
            return false;
        }
        foreach ($jsonobj['coveragegroups'] as $coverage_group) {
            $group_name = $coverage_group['label'];
            if ($group_name === 'Total') {
                if ($coverage_group['loctested'] !== 25) {
                    $this->fail('Expected 25 total loctested');
                }
                if ($coverage_group['locuntested'] !== 10) {
                    $this->fail('Expected 10 total locuntested');
                }
                continue;
            }
            $coverage = array_pop($coverage_group['coverages']);
            switch ($group_name) {
                case 'Third Party':
                    $success &=
                        $this->checkCoverage($coverage, 9, 4, $group_name);
                    break;
                case 'Experimental':
                    $success &=
                        $this->checkCoverage($coverage, 8, 3, $group_name);
                    break;
                case 'Production':
                    $success &=
                        $this->checkCoverage($coverage, 8, 3, $group_name);
                    break;
                default:
                    $this->fail("Unexpected group $group_name");
            }
        }
        return $success;
    }

    public function verifyAggregate()
    {
        // Since we only have one nightly coverage build, the aggregate won't
        // appear on index.php.  So instead we have to verify that it is correct
        // by querying the database.
        $success = true;

        // Get parentid.
        $row = DB::select(
            "SELECT id FROM build
                WHERE name = 'Aggregate Coverage' AND
                parentid=-1 AND
                projectid=
                (SELECT id FROM project WHERE name='CrossSubProjectExample')")[0];
        $parentid = $row->id;
        if (empty($parentid) || $parentid < 1) {
            $this->fail('No aggregate parentid found when expected');
            return false;
        }

        // Verify parent results.
        $row = DB::select("SELECT * from coveragesummary WHERE buildid='$parentid'")[0];
        $success &= $this->checkCoverage($row, 25, 10, 'aggregate parent');

        // Verify child results.
        $result = DB::select("
                SELECT cs.loctested, cs.locuntested, spg.name
                FROM build AS b
                INNER JOIN coveragesummary AS cs ON (b.id=cs.buildid)
                INNER JOIN subproject AS sp ON (b.subprojectid=sp.id)
                INNER JOIN subprojectgroup AS spg ON (sp.groupid=spg.id)
                WHERE parentid='$parentid'");
        $num_builds = count($result);
        if ($num_builds !== 3) {
            $this->fail("Expected 3 aggregate children, found $num_builds");
            return false;
        }
        foreach ($result as $row) {
            $group_name = $row->name;
            switch ($group_name) {
                case 'Third Party':
                    $success &=
                        $this->checkCoverage($row, 9, 4, "aggregate $group_name");
                    break;
                case 'Experimental':
                    $success &=
                        $this->checkCoverage($row, 8, 3, "aggregate $group_name");
                    break;
                case 'Production':
                    $success &=
                        $this->checkCoverage($row, 8, 3, "aggregate $group_name");
                    break;
                default:
                    $this->fail("Unexpected aggregate group $group_name");
            }
        }
        return $success;
    }

    public function checkCoverage($coverage, $expected_loctested, $expected_locuntested, $name)
    {
        $coverage = collect($coverage)->toArray();
        if ($coverage['loctested'] != $expected_loctested) {
            $this->fail("Expected $name loctested to be $expected_loctested, found " . $coverage['loctested']);
            return false;
        }
        if ($coverage['locuntested'] != $expected_locuntested) {
            $this->fail("Expected $name locuntested to be $expected_locuntested, found " . $coverage['locuntested']);
            return false;
        }
        return true;
    }
}

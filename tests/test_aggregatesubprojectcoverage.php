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

class AggregateSubProjectCoverageTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
        $this->deleteLog($this->logfilename);
        $this->DataDir = dirname(__FILE__) . '/data/AggregateSubProjectCoverage';
    }

    public function testSubmitCoverage()
    {
        $files = array(
            'debug_case/experimental/Coverage.xml',
            'debug_case/experimental/CoverageLog-0.xml',
            'debug_case/production/Coverage.xml',
            'debug_case/production/CoverageLog-0.xml',
            'debug_case/thirdparty/Coverage.xml',
            'debug_case/thirdparty/CoverageLog-0.xml',
            'debug_case/nofiles/Coverage.xml',
            'debug_case/nofiles/CoverageLog-0.xml',
            'release_case/experimental/Coverage.xml',
            'release_case/experimental/CoverageLog-0.xml',
            'release_case/production/Coverage.xml',
            'release_case/production/CoverageLog-0.xml',
            'release_case/thirdparty/Coverage.xml',
            'release_case/thirdparty/CoverageLog-0.xml'
            );
        foreach ($files as $filename) {
            $file_to_submit = "$this->DataDir/$filename";
            if (!$this->submission('CrossSubProjectExample', $file_to_submit)) {
                $this->fail("Failed to submit $filename");
                return 1;
            }
        }
        $this->pass('Test passed');
    }

    public function testVerifyResults()
    {
        $success = true;

        // Verify parent results.
        $this->get($this->url . '/api/v1/index.php?project=CrossSubProjectExample&date=2016-02-16');
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);

        $num_builds = count($jsonobj['buildgroups'][0]['builds']);
        if ($num_builds != 2) {
            $this->fail("Expected 2 builds, found $num_builds");
            return 1;
        }

        $num_coverages = count($jsonobj['coverages']);
        if ($num_coverages != 3) {
            $this->fail("Expected 3 coverages, found $num_coverages");
            return 1;
        }

        $debug_buildid = 0;
        $release_buildid = 0;
        $aggregate_buildid = 0;
        $success = true;
        foreach ($jsonobj['coverages'] as $coverage) {
            $name = $coverage['buildname'];
            switch ($name) {
                case 'Aggregate Coverage':
                    $aggregate_buildid = $coverage['buildid'];
                    $success &=
                        $this->checkCoverage($coverage, 23, 6, 79.31);
                    break;
                case 'release_coverage':
                    $release_buildid = $coverage['buildid'];
                    $success &=
                        $this->checkCoverage($coverage, 15, 14, 51.72);
                    break;
                case 'debug_coverage':
                    $debug_buildid = $coverage['buildid'];
                    $success &=
                        $this->checkCoverage($coverage, 20, 9, 68.97);
                    break;
                default:
                    $this->fail("Unexpected coverage $name");
                    return 1;
            }
        }

        // Verify child results.
        $success &= $this->verifyChildResult($debug_buildid,
            5, 0, 5, 3, 0, 0, 10, 6);
        $success &= $this->verifyChildResult($release_buildid,
            3, 2, 4, 4, 8, 8);
        $success &= $this->verifyChildResult($aggregate_buildid,
            5, 0, 6, 2, 0, 0, 12, 4);

        return $success;
    }

    public function checkCoverage($coverage, $expected_loctested,
                                  $expected_locuntested, $name)
    {
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

    public function verifyChildResult($parentid,
        $thirdparty_loctested, $thirdparty_locuntested,
        $experimental_loctested, $experimental_locuntested,
        $production_loctested, $production_locuntested)
    {
        $success = true;
        $this->get($this->url . "/api/v1/index.php?project=CrossSubProjectExample&parentid=$parentid");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $num_groups = count($jsonobj['coveragegroups']);
        if ($num_groups != 3) {
            $this->fail("Expected 3 coverage groups, found $num_groups");
            return false;
        }
        foreach ($jsonobj['coveragegroups'] as $coverage_group) {
            $coverage = array_pop($coverage_group['coverages']);
            $group_name = $coverage_group['label'];
            switch ($group_name) {
                case 'Third Party':
                    $success &=
                        $this->checkCoverage($coverage, $thirdparty_loctested,
                            $thirdparty_locuntested, $group_name);
                    break;
                case 'Experimental':
                    $success &=
                        $this->checkCoverage($coverage, $experimental_loctested,
                            $experimental_locuntested, $group_name);
                    break;
                case 'Production':
                    $success &=
                        $this->checkCoverage($coverage, $production_loctested,
                            $production_locuntested, $group_name);
                    break;
                default:
                    $this->fail("Unexpected group $group_name");
            }
        }
        return $success;
    }
}

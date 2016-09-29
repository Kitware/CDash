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
            'release_case/thirdparty/CoverageLog-0.xml',
            'release_case/releaseonly/Coverage.xml',
            'release_case/releaseonly/CoverageLog-0.xml'
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
                        $this->checkCoverage($coverage, 27, 10, 72.97, $name);
                    break;
                case 'release_coverage':
                    $release_buildid = $coverage['buildid'];
                    $success &=
                        $this->checkCoverage($coverage, 19, 18, 51.35, $name);
                    break;
                case 'debug_coverage':
                    $debug_buildid = $coverage['buildid'];
                    $success &=
                        $this->checkCoverage($coverage, 20, 9, 68.97, $name);
                    break;
                default:
                    $this->fail("Unexpected coverage $name");
                    return 1;
            }
        }

        // Verify child results.

        // 'debug_coverage'
        $experimental = array();
        $experimental['MyExperimentalFeature'] = array();
        $experimental['MyExperimentalFeature']['loctested'] = 5;
        $experimental['MyExperimentalFeature']['locuntested'] = 3;
        $experimental['MyExperimentalFeature']['percentage'] = 62.5;
        $success &= $this->verifyChildResult($debug_buildid, "Experimental", $experimental);

        $third_party = array();
        $third_party['MyThirdPartyDependency'] = array();
        $third_party['MyThirdPartyDependency']['loctested'] = 5;
        $third_party['MyThirdPartyDependency']['locuntested'] = 0;
        $third_party['MyThirdPartyDependency']['percentage'] = 100.0;
        $success &= $this->verifyChildResult($debug_buildid, "Third Party", $third_party);

        $production = array();
        $production['MyEmptyCoverage'] = array();
        $production['MyEmptyCoverage']['loctested'] = 0;
        $production['MyEmptyCoverage']['locuntested'] = 0;
        $production['MyEmptyCoverage']['percentage'] = 100.0;
        $production['MyProductionCode'] = array();
        $production['MyProductionCode']['loctested'] = 10;
        $production['MyProductionCode']['locuntested'] = 6;
        $production['MyProductionCode']['percentage'] = 62.5;
        $success &= $this->verifyChildResult($debug_buildid, "Production", $production);

        // 'release_coverage'
        $experimental = array();
        $experimental['MyExperimentalFeature'] = array();
        $experimental['MyExperimentalFeature']['loctested'] = 4;
        $experimental['MyExperimentalFeature']['locuntested'] = 4;
        $experimental['MyExperimentalFeature']['percentage'] = 50.0;
        $success &= $this->verifyChildResult($release_buildid, "Experimental", $experimental);

        $third_party = array();
        $third_party['MyThirdPartyDependency'] = array();
        $third_party['MyThirdPartyDependency']['loctested'] = 3;
        $third_party['MyThirdPartyDependency']['locuntested'] = 2;
        $third_party['MyThirdPartyDependency']['percentage'] = 60.0;
        $third_party['MyReleaseOnlyFeature'] = array();
        $third_party['MyReleaseOnlyFeature']['loctested'] = 4;
        $third_party['MyReleaseOnlyFeature']['locuntested'] = 4;
        $third_party['MyReleaseOnlyFeature']['percentage'] = 50.0;
        $success &= $this->verifyChildResult($release_buildid, "Third Party", $third_party);

        $production = array();
        $production['MyProductionCode'] = array();
        $production['MyProductionCode']['loctested'] = 8;
        $production['MyProductionCode']['locuntested'] = 8;
        $production['MyProductionCode']['percentage'] = 50.0;
        $success &= $this->verifyChildResult($release_buildid, "Production", $production);

        // 'Aggregate Coverage'
        $experimental = array();
        $experimental['MyExperimentalFeature'] = array();
        $experimental['MyExperimentalFeature']['loctested'] = 6;
        $experimental['MyExperimentalFeature']['locuntested'] = 2;
        $experimental['MyExperimentalFeature']['percentage'] = 75.0;
        $success &= $this->verifyChildResult($aggregate_buildid, "Experimental", $experimental);

        $third_party = array();
        $third_party['MyThirdPartyDependency'] = array();
        $third_party['MyThirdPartyDependency']['loctested'] = 5;
        $third_party['MyThirdPartyDependency']['locuntested'] = 0;
        $third_party['MyThirdPartyDependency']['percentage'] = 100.0;
        $third_party['MyReleaseOnlyFeature'] = array();
        $third_party['MyReleaseOnlyFeature']['loctested'] = 4;
        $third_party['MyReleaseOnlyFeature']['locuntested'] = 4;
        $third_party['MyReleaseOnlyFeature']['percentage'] = 50.0;
        $success &= $this->verifyChildResult($aggregate_buildid, "Third Party", $third_party);

        $production = array();
        $production['MyEmptyCoverage'] = array();
        $production['MyEmptyCoverage']['loctested'] = 0;
        $production['MyEmptyCoverage']['locuntested'] = 0;
        $production['MyEmptyCoverage']['percentage'] = 100.0;
        $production['MyProductionCode'] = array();
        $production['MyProductionCode']['loctested'] = 12;
        $production['MyProductionCode']['locuntested'] = 4;
        $production['MyProductionCode']['percentage'] = 75.0;
        $success &= $this->verifyChildResult($aggregate_buildid, "Production", $production);

        return $success;
    }

    public function checkCoverage($coverage, $expected_loctested,
                                  $expected_locuntested, $expected_percentage,
                                  $name)
    {
        if ($coverage['loctested'] != $expected_loctested) {
            $this->fail("Expected $name loctested to be $expected_loctested, found " . $coverage['loctested'] .  " " . $coverage['locuntested'] .  " " . $coverage['percentage']);
            return false;
        }
        if ($coverage['locuntested'] != $expected_locuntested) {
            $this->fail("Expected $name locuntested to be $expected_locuntested, found " . $coverage['locuntested']);
            return false;
        }
        if ($coverage['percentage'] != $expected_percentage) {
            $this->fail("Expected $name percentage to be $expected_percentage, found " . $coverage['percentage']);
            return false;
        }
        return true;
    }

    public function verifyChildResult($parentid, $group_name, $to_find)
    {
        $success = true;
        $this->get($this->url . "/api/v1/index.php?project=CrossSubProjectExample&parentid=$parentid");
        $content = $this->getBrowser()->getContent();
        $jsonobj = json_decode($content, true);
        $num_groups = count($jsonobj['coveragegroups']);
        if ($num_groups != 4) {
            $this->fail("Expected 4 coverage groups, found $num_groups");
            return false;
        }
        foreach ($jsonobj['coveragegroups'] as $coverage_group) {
            if ($coverage_group['label'] == $group_name) {
                foreach ($coverage_group['coverages'] as $coverage) {
                    $subproject_name = $coverage['label'];
                    $success &=
                      $this->checkCoverage($coverage, $to_find[$subproject_name]['loctested'],
                      $to_find[$subproject_name]['locuntested'], $to_find[$subproject_name]['percentage'],
                      $subproject_name);
                }
                break;
            }
        }
        return $success;
    }
}

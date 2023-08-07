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

//
// After including cdash_test_case.php, subsequent require_once calls are
// relative to the top of the CDash source tree
//
require_once dirname(__FILE__) . '/cdash_test_case.php';

require_once 'include/pdo.php';

class AggregateCoverageTestCase extends KWWebTestCase
{
    public function __construct()
    {
        parent::__construct();
    }

    public function testAggregateCoverage()
    {
        $files = array(
            'debug_case/Coverage.xml',
            'debug_case/CoverageLog-0.xml',
            'release_case/Coverage.xml',
            'release_case/CoverageLog-0.xml');

        foreach ($files as $file) {
            if (!$this->submitTestingFile($file)) {
                $this->fail("Failed to submit $file");
                return 1;
            }
        }

        $this->get($this->url . '/api/v1/index.php?project=InsightExample&date=2016-02-16');
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
                        $this->checkCoverage($coverage, 23, 14, 62.16);
                    break;
                case 'release_coverage':
                    $release_buildid = $coverage['buildid'];
                    $success &=
                        $this->checkCoverage($coverage, 15, 22, 40.54);
                    break;
                case 'debug_coverage':
                    $debug_buildid = $coverage['buildid'];
                    $success &=
                        $this->checkCoverage($coverage, 20, 17, 54.05);
                    break;
                default:
                    $this->fail("Unexpected coverage $name");
                    return 1;
            }
        }

        // Verify the differing file case.  The aggregate should share its version of
        // 'diff.cxx' with debug_coverage because it submitted first.
        $query = "SELECT buildid, fileid FROM coverage AS c
            INNER JOIN coveragefile AS cf ON (cf.id=c.fileid)
            WHERE cf.fullpath='./diff.cxx'";
        $result = pdo_query($query);
        $num_rows = pdo_num_rows($result);
        if ($num_rows != 3) {
            $this->fail("Expected 3 rows, found $num_rows");
            return 1;
        }
        $debug_fileid = 0;
        $release_fileid = 0;
        $aggregate_fileid = 0;
        while ($row = pdo_fetch_array($result)) {
            $buildid = $row['buildid'];
            switch ($buildid) {
                case $debug_buildid:
                    $debug_fileid = $row['fileid'];
                    break;
                case $release_buildid:
                    $release_fileid = $row['fileid'];
                    break;
                case $aggregate_buildid:
                    $aggregate_fileid = $row['fileid'];
                    break;
                default:
                    $this->fail("Unexpected buildid $buildid");
                    return 1;
            }
        }
        if ($aggregate_fileid !== $debug_fileid) {
            $this->fail("Expected aggregate and debug to share a version of diff.cxx ($aggregate_fileid vs $debug_fileid");
            return 1;
        }
        if ($aggregate_fileid === $release_fileid) {
            $this->fail("Expected aggregate and release to have different versions of diff.cxx ($aggregate_fileid vs $release_fileid");
            return 1;
        }

        if ($success) {
            $this->pass('Tests passed');
            return 0;
        } else {
            return 1;
        }
    }

    public function checkCoverage($coverage, $expected_loctested,
        $expected_locuntested, $expected_percentage)
    {
        if ($coverage['loctested'] != $expected_loctested) {
            $this->fail('Expected ' . $coverage['buildname'] . " loctested to be $expected_loctested, found " . $coverage['loctested']);
            return false;
        }
        if ($coverage['locuntested'] != $expected_locuntested) {
            $this->fail('Expected ' . $coverage['buildname'] . " locuntested to be $expected_locuntested, found " . $coverage['locuntested']);
            return false;
        }
        if ($coverage['percentage'] != $expected_percentage) {
            $this->fail('Expected ' . $coverage['buildname'] . " percentage to be $expected_percentage, found " . $coverage['percentage']);
            return false;
        }
        return true;
    }

    public function submitTestingFile($filename)
    {
        $file_to_submit =
            dirname(__FILE__) . '/data/AggregateCoverage/' . $filename;
        return $this->submission('InsightExample', $file_to_submit);
    }
}

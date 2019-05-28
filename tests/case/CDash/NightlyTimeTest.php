<?php
/**
 * =========================================================================
 *   Program:   CDash - Cross-Platform Dashboard System
 *   Module:    $Id$
 *   Language:  PHP
 *   Date:      $Date$
 *   Version:   $Revision$
 *   Copyright (c) Kitware, Inc. All rights reserved.
 *   See LICENSE or http://www.cdash.org/licensing/ for details.
 *   This software is distributed WITHOUT ANY WARRANTY; without even
 *   the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
 *   PURPOSE. See the above copyright notices for more information.
 * =========================================================================
 */

use CDash\Model\Project;

class NightlyTimeTest extends PHPUnit_Framework_TestCase
{
    public function __construct()
    {
        $this->Project = new Project();
    }

    public function testBuildDateWithConsistentTimeZones()
    {
        date_default_timezone_set('UTC');

        // "Nightly" time in the morning.
        $this->Project->NightlyTime = '11:59:59 UTC';

        // Build started before the nightly time.
        // It belongs to yesterday.
        $this->validateTestingDay('2019-05-17 11:59:58', '2019-05-16');

        // Build started at or after the nightly time.
        // It belongs to today.
        $this->validateTestingDay('2019-05-17 11:59:59', '2019-05-17');

        // "Nightly" time in the afternoon.
        $this->Project->NightlyTime = '12:00:01 UTC';

        // Build started before the nightly time.
        // It belongs to today.
        $this->validateTestingDay('2019-05-17 11:59:59', '2019-05-17');

        // Equal to or after the nightly time:
        // It belongs to tomorrow.
        $this->validateTestingDay('2019-05-17 12:00:01', '2019-05-18');
    }

    public function testBuildDateWithDifferentTimeZones()
    {
        date_default_timezone_set('UTC');

        // "Nightly" time in the morning (according to server time zone).
        $this->Project->NightlyTime = '07:59:59 America/New_York';

        // Build started before the nightly time.
        // It belongs to yesterday.
        $this->validateTestingDay('2019-05-17 11:59:58', '2019-05-16');

        // Build started at or after the nightly time.
        // It belongs to today.
        $this->validateTestingDay('2019-05-17 12:00:01', '2019-05-17');

        // "Nightly" time in the afternoon according to server time zone,
        // even though it looks like a time in the morning.
        $this->Project->NightlyTime = '08:00:01 America/New_York';

        // Build started before the nightly time.
        // It belongs to today.
        $this->validateTestingDay('2019-05-17 11:59:59', '2019-05-17');

        // Equal to or after the nightly time:
        // It belongs to tomorrow.
        $this->validateTestingDay('2019-05-17 12:00:01', '2019-05-18');
    }

    public function testBuildDateAcrossDST()
    {
        date_default_timezone_set('America/New_York');
        $this->Project->NightlyTime = '01:00:00 America/New_York';

        // DST 2019 in New York began at 2:00 AM on Sunday, March 10
        $this->validateTestingDay('2019-03-10 00:59:59', '2019-03-09');
        $this->validateTestingDay('2019-03-10 01:00:01', '2019-03-10');

        // DST 2018 in New York ended at 2:00 AM on Sunday, November 4
        $this->validateTestingDay('2018-11-04 00:59:59', '2018-11-03');
        $this->validateTestingDay('2019-11-04 01:00:01', '2019-11-04');
    }

    private function validateTestingDay($starttime, $expected)
    {
        $actual = $this->Project->GetTestingDay($starttime);
        $this->assertEquals($expected, $actual);
    }
}

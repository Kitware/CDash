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
use Tests\TestCase;

class NightlyTimeTest extends TestCase
{
    public function __construct()
    {
        parent::__construct();
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
        $utc_time = new \DateTimeZone('UTC');

        // DST 2019 in New York began at 2:00 AM on Sunday, March 10
        $datetime = new \DateTime('2019-03-10 00:59:59');
        $datetime->setTimezone($utc_time);
        $this->validateTestingDay($datetime->format('Y-m-d H:i:s'), '2019-03-09');

        $datetime = new \DateTime('2019-03-10 01:00:01');
        $datetime->setTimezone($utc_time);
        $this->validateTestingDay($datetime->format('Y-m-d H:i:s'), '2019-03-10');

        // DST 2018 in New York ended at 2:00 AM on Sunday, November 4
        $datetime = new \DateTime('2018-11-04 00:59:59');
        $datetime->setTimezone($utc_time);
        $this->validateTestingDay($datetime->format('Y-m-d H:i:s'), '2018-11-03');

        $datetime = new \DateTime('2018-11-04 01:00:01');
        $datetime->setTimezone($utc_time);
        $this->validateTestingDay($datetime->format('Y-m-d H:i:s'), '2018-11-04');
    }

    public function testUTCInput()
    {
        date_default_timezone_set('America/Denver');
        $this->Project->NightlyTime = '04:01:00 UTC';
        $this->validateTestingDay('2019-09-26 04:00:59 UTC', '2019-09-25');

        $this->validateTestingDay('2020-03-09 04:00:59 UTC', '2020-03-08');
    }

    private function validateTestingDay($starttime, $expected)
    {
        $actual = $this->Project->GetTestingDay($starttime);
        $this->assertEquals($expected, $actual);
    }
}

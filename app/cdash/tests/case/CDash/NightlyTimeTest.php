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

use App\Utils\TestingDay;

use CDash\Model\Project;
use Tests\TestCase;

class NightlyTimeTest extends TestCase
{
    public function __construct(mixed ...$params)
    {
        parent::__construct(...$params);
        $this->Project = new Project();
        // Avoid the database for this test.
        $this->Project->Filled = true;
    }

    public function testBuildDateWithConsistentTimeZones()
    {
        // "Nightly" time in the morning.
        $this->Project->SetNightlyTime('11:59:59 UTC');

        // Build started before the nightly time.
        // It belongs to yesterday.
        $this->validateTestingDay('2019-05-17 11:59:58', '2019-05-16');

        // Build started at or after the nightly time.
        // It belongs to today.
        $this->validateTestingDay('2019-05-17 11:59:59', '2019-05-17');

        // "Nightly" time in the afternoon.
        $this->Project->SetNightlyTime('12:00:01 UTC');

        // Build started before the nightly time.
        // It belongs to today.
        $this->validateTestingDay('2019-05-17 11:59:59', '2019-05-17');

        // Equal to or after the nightly time:
        // It belongs to tomorrow.
        $this->validateTestingDay('2019-05-17 12:00:01', '2019-05-18');
    }

    public function testBuildDateWithDifferentTimeZones()
    {
        // "Nightly" time in the morning according to the project's time zone.
        $this->Project->SetNightlyTime('11:59:59 America/New_York');

        // Build started before the nightly time.
        // It belongs to yesterday.
        $this->validateTestingDay('2019-05-17 15:59:58', '2019-05-16');

        // Build started at or after the nightly time.
        // It belongs to today.
        $this->validateTestingDay('2019-05-17 16:00:01', '2019-05-17');

        // "Nightly" time in the afternoon according to the project's time zone.
        $this->Project->SetNightlyTime('12:00:01 America/New_York');

        // Build started before the nightly time.
        // It belongs to today.
        $this->validateTestingDay('2019-05-17 15:59:59', '2019-05-17');

        // Equal to or after the nightly time:
        // It belongs to tomorrow.
        $this->validateTestingDay('2019-05-17 16:00:02', '2019-05-18');
    }

    public function testBuildDateAcrossDST()
    {
        $this->Project->SetNightlyTime('01:00:00 America/New_York');
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
        $this->Project->SetNightlyTime('04:01:00 UTC');
        $this->validateTestingDay('2019-09-26 04:00:59', '2019-09-25');

        $this->validateTestingDay('2020-03-09 04:00:59', '2020-03-08');
    }

    public function testInvalidTimezone()
    {
        $this->Project->SetNightlyTime('04:01:00 XYZ');
        $this->validateTestingDay('2019-09-26 04:00:59', '2019-09-25');

        $this->validateTestingDay('2020-03-09 04:00:59', '2020-03-08');
    }

    private function validateTestingDay($starttime, $expected)
    {
        $actual = TestingDay::get($this->Project, $starttime);
        $this->assertEquals($expected, $actual);
    }
}

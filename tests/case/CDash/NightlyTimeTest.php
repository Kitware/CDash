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

require_once 'include/common.php';

class NightlyTimeTest extends PHPUnit_Framework_TestCase
{
    public function testBuildDateWithConsistentTimeZones()
    {
        date_default_timezone_set('UTC');

        // "Nightly" time in the morning.
        $nightlytime = '11:59:59 UTC';

        // Build started before the nightly time.
        // It belongs to yesterday.
        $this->validateNightlyTime($nightlytime, '2019-05-17 11:59:58', '2019-05-16');

        // Build started at or after the nightly time.
        // It belongs to today.
        $this->validateNightlyTime($nightlytime, '2019-05-17 11:59:59', '2019-05-17');

        // "Nightly" time in the afternoon.
        $nightlytime = '12:00:01 UTC';

        // Build started before the nightly time.
        // It belongs to today.
        $this->validateNightlyTime($nightlytime, '2019-05-17 11:59:59', '2019-05-17');

        // Equal to or after the nightly time:
        // It belongs to tomorrow.
        $this->validateNightlyTime($nightlytime, '2019-05-17 12:00:01', '2019-05-18');
    }

    public function testBuildDateWithDifferentTimeZones()
    {
        date_default_timezone_set('UTC');

        // "Nightly" time in the morning (according to server time zone).
        $nightlytime = '07:59:59 America/New_York';

        // Build started before the nightly time.
        // It belongs to yesterday.
        $this->validateNightlyTime($nightlytime, '2019-05-17 11:59:58', '2019-05-16');

        // Build started at or after the nightly time.
        // It belongs to today.
        $this->validateNightlyTime($nightlytime, '2019-05-17 12:00:01', '2019-05-17');

        // "Nightly" time in the afternoon according to server time zone,
        // even though it looks like a time in the morning.
        $nightlytime = '08:00:01 America/New_York';

        // Build started before the nightly time.
        // It belongs to today.
        $this->validateNightlyTime($nightlytime, '2019-05-17 11:59:59', '2019-05-17');

        // Equal to or after the nightly time:
        // It belongs to tomorrow.
        $this->validateNightlyTime($nightlytime, '2019-05-17 12:00:01', '2019-05-18');
    }

    public function testBuildDateAcrossDST()
    {
        date_default_timezone_set('America/New_York');
        $nightlytime = '01:00:00 America/New_York';

        // DST 2019 in New York began at 2:00 AM on Sunday, March 10
        $this->validateNightlyTime($nightlytime, '2019-03-10 00:59:59', '2019-03-09');
        $this->validateNightlyTime($nightlytime, '2019-03-10 01:00:01', '2019-03-10');

        // DST 2018 in New York ended at 2:00 AM on Sunday, November 4
        $this->validateNightlyTime($nightlytime, '2018-11-04 00:59:59', '2018-11-03');
        $this->validateNightlyTime($nightlytime, '2019-11-04 01:00:01', '2019-11-04');
    }

    private function validateNightlyTime($nightlytime, $starttime, $expected)
    {
        $actual = get_dashboard_date_from_build_starttime($starttime, $nightlytime);
        $this->assertEquals($expected, $actual);
    }
}

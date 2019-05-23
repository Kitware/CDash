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
    public function testBuildDateWithUTCNightlyTime()
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

    public function testBuildDateWithEasternNightlyTime()
    {
        date_default_timezone_set('America/New_York');

        // "Nightly" time in the morning.
        $nightlytime = '11:59:59 America/New_York';

        // Build started before the nightly time.
        // It belongs to yesterday.
        $this->validateNightlyTime($nightlytime, '2019-05-17 15:59:58', '2019-05-16');

        // Build started at or after the nightly time.
        // It belongs to today.
        $this->validateNightlyTime($nightlytime, '2019-05-17 15:59:59', '2019-05-17');

        // "Nightly" time in the afternoon.
        $nightlytime = '12:00:01 America/New_York';

        // Build started before the nightly time.
        // It belongs to today.
        $this->validateNightlyTime($nightlytime, '2019-05-17 15:59:59', '2019-05-17');

        // Equal to or after the nightly time:
        // It belongs to tomorrow.
        $this->validateNightlyTime($nightlytime, '2019-05-17 16:00:01', '2019-05-18');

        // Fun with DST.
        // DST 2019 in New York began at 2:00 AM on Sunday, March 10
        $nightlytime = '01:00:00 America/New_York';
        $this->validateNightlyTime($nightlytime, '2019-03-10 00:59:59', '2019-03-09');
        // BUG: No buildtime seems to want to return 03-10 in this scenario.
        //$this->validateNightlyTime($nightlytime, '2019-03-11 00:59:59', '2019-03-10');

        // DST 2018 ended at 2:00 AM on Sunday, November 4
        $this->validateNightlyTime($nightlytime, '2018-11-04 00:59:59', '2018-11-03');
        // BUG: 01:00:01 should be after the nightly time, but it is not.
    }

    private function validateNightlyTime($nightlytime, $starttime, $expected)
    {
        $actual = get_dashboard_date_from_build_starttime($starttime, $nightlytime);
        $this->assertEquals($expected, $actual);
    }
}

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

namespace App\Services;

use CDash\Model\Project;

/**
 * This class handles testing day bounds checks.
 **/
class TestingDay
{
    /**
     * Return the testing day (a string in DATETIME format)
     * for a given date (a date/time string relative to UTC).
     * For example, if the build was started after the nightly start time
     * then it should appear on the dashboard results for the subsequent day.
     *
     * As a side effect, this function also changes the default timezone to
     * the one specified in the project's settings.
     */
    public static function get(Project $project, $date)
    {
        // Make sure the project is populated from the database.
        $project->Fill();

        // Extract hour, minute, and second from the project nightly start time.
        $current_nightly_timestamp = $project->NightlyDateTime->getTimestamp();
        $hour = intval(date('H', $current_nightly_timestamp));
        $minute = intval(date('i', $current_nightly_timestamp));
        $second = intval(date('s', $current_nightly_timestamp));

        // Get UNIX timestamp for input date (interpreted as UTC time).
        $build_datetime = new \DateTime($date . ' UTC');
        $build_start_timestamp = $build_datetime->getTimestamp();

        // Generate a DateTime object for our nightly starttime on the date
        // of the build and use it to get a corresponding UNIX timestamp.
        $year = intval(date('Y', $build_start_timestamp));
        $month = intval(date('n', $build_start_timestamp));
        $day = intval(date('j', $build_start_timestamp));

        $nightly_datetime = new \DateTime();
        $nightly_datetime->setTimezone($project->NightlyTimezone);
        $nightly_datetime->setDate($year, $month, $day);
        $nightly_datetime->setTime($hour, $minute, $second);
        $nightly_start_timestamp = $nightly_datetime->getTimestamp();

        if (date(FMT_TIME, $nightly_start_timestamp) < '12:00:00') {
            // If the "nightly" start time is in the morning then any build
            // that occurs before it is part of the previous testing day.
            if (date(FMT_TIME, $build_start_timestamp) <
                    date(FMT_TIME, $nightly_start_timestamp)
            ) {
                $build_datetime->sub(new \DateInterval('P1D'));
                $build_start_timestamp = $build_datetime->getTimestamp();
            }
        } else {
            // If the nightly start time is NOT in the morning then any build
            // that occurs after it is part of the next testing day.
            if (date(FMT_TIME, $build_start_timestamp) >=
                    date(FMT_TIME, $nightly_start_timestamp)
            ) {
                $build_datetime->add(new \DateInterval('P1D'));
                $build_start_timestamp = $build_datetime->getTimestamp();
            }
        }

        return date(FMT_DATE, $build_start_timestamp);
    }
}

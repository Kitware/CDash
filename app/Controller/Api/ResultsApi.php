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
namespace CDash\Controller\Api;

use CDash\Database;
use CDash\Model\Build;
use CDash\Model\Project;

/**
 * Parent class for all API controllers responsible for displaying
 * build/test results.
 **/
class ResultsApi extends ProjectApi
{
    protected $beginDate;
    protected $currentStartTime;
    protected $date;
    protected $endDate;
    protected $nextDate;
    protected $previousDate;

    private $datetime;

    public function __construct(Database $db, Project $project)
    {
        parent::__construct($db, $project);
        $this->beginDate = self::BEGIN_EPOCH;
        $this->currentStartTime = 0;
        $this->date = null;
        $this->endDate = self::BEGIN_EPOCH;
        $this->nextDate = '';
        $this->previousDate = '';

        $this->datetime = new \DateTime();
    }

    public function getBeginDate()
    {
        return $this->beginDate;
    }

    public function getCurrentStartTime()
    {
        return $this->currentStartTime;
    }

    public function getDate()
    {
        return $this->date;
    }

    public function getEndDate()
    {
        return $this->endDate;
    }

    public function setDate($date)
    {
        list($previousdate, $beginning_timestamp, $nextdate, $d) =
            get_dates($date, $this->project->NightlyTime);
        if (is_null($date)) {
            $date = $d;
        }
        $this->date = $date;
        $this->currentStartTime = $beginning_timestamp;
        $this->nextDate = $nextdate;
        $this->previousDate = $previousdate;
        $this->beginDate = gmdate(FMT_DATETIME, $beginning_timestamp);

        $this->datetime->setTimeStamp($beginning_timestamp);
        $this->datetime->add(new \DateInterval('P1D'));
        $end_timestamp = $this->datetime->getTimestamp();
        $this->endDate = gmdate(FMT_DATETIME, $end_timestamp);
    }

    public function determineDateRange(&$response)
    {
        if (isset($_REQUEST['begin']) || isset($_REQUEST['end'])) {
            // Honor 'begin' & 'end' parameters to specify a range of dates.
            if (isset($_REQUEST['begin']) && isset($_REQUEST['end'])) {
                // Compute a date range if both arguments were specified.
                $begin = $_REQUEST['begin'];
                list($unused, $beginning_timestamp) =
                    get_dates($begin, $this->project->NightlyTime);
                $this->currentStartTime = $beginning_timestamp;
                $this->beginDate = gmdate(FMT_DATETIME, $beginning_timestamp);
                $response['begin'] = $begin;

                $this->date = $_REQUEST['end'];
                $response['end'] = $this->date;
                list($previousdate, $end_timestamp, $nextdate) =
                    get_dates($this->date, $this->project->NightlyTime);
                $this->previousDate = $previousdate;
                $this->nextDate = $nextdate;

                $this->datetime->setTimeStamp($end_timestamp);
                $this->datetime->add(new \DateInterval('P1D'));
                $end_timestamp = $this->datetime->getTimestamp();
                $this->endDate = gmdate(FMT_DATETIME, $end_timestamp);
            } else {
                // If not, just use whichever one was set.
                if (isset($_REQUEST['begin'])) {
                    $this->date = $_REQUEST['begin'];
                } else {
                    $this->date = $_REQUEST['end'];
                }
            }
        } elseif (isset($_REQUEST['date'])) {
            $this->date = $_REQUEST['date'];
        } else {
            // No date specified. Look up the most recent date with results.
            $stmt = $this->db->prepare('
                SELECT starttime FROM build
                WHERE projectid = :projectid
                ORDER BY starttime DESC LIMIT 1');
            $this->db->execute($stmt, [':projectid' => $this->project->Id]);
            $starttime = $stmt->fetchColumn();
            if ($starttime) {
                $this->date = $this->project->GetTestingDay($starttime);
            }
        }

        if ($this->beginDate == self::BEGIN_EPOCH) {
            $this->setDate($this->date);
        }
    }
}

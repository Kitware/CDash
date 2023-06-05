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

use App\Services\TestingDay;

use CDash\Database;
use CDash\Model\Build;
use CDash\Model\Project;

/**
 * Parent class for all API controllers responsible for displaying
 * build/test results.
 **/
abstract class ResultsApi extends ProjectApi
{
    public $filterdata;

    protected $filterSQL;
    protected $limitSQL;
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

        $this->filterdata = [];

        $this->beginDate = self::BEGIN_EPOCH;
        $this->currentStartTime = 0;
        $this->date = null;
        $this->endDate = self::BEGIN_EPOCH;
        $this->filterSQL = '';
        $this->limitSQL = '';
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

    public function validateDateString($date)
    {
        $dt = \DateTime::createFromFormat("Y-m-d", $date);
        if ($dt !== false && !array_sum($dt::getLastErrors())) {
            return $date;
        }
        return false;
    }

    public function setDate($date)
    {
        $this->project->Fill();
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
        $date_set = false;

        $begin = false;
        if (isset($_REQUEST['begin'])) {
            $begin = $this->validateDateString($_REQUEST['begin']);
        }

        $end = false;
        if (isset($_REQUEST['end'])) {
            $end = $this->validateDateString($_REQUEST['end']);
        }

        if ($begin && $end) {
            // Honor 'begin' & 'end' parameters to specify a range of dates.
            // Compute a date range if both arguments were specified.
            list($unused, $beginning_timestamp) =
                get_dates($begin, $this->project->NightlyTime);
            $this->currentStartTime = $beginning_timestamp;
            $this->beginDate = gmdate(FMT_DATETIME, $beginning_timestamp);
            $response['begin'] = $begin;

            $this->date = $end;
            $response['end'] = $this->date;
            list($previousdate, $end_timestamp, $nextdate) =
                get_dates($this->date, $this->project->NightlyTime);
            $this->previousDate = $previousdate;
            $this->nextDate = $nextdate;

            $this->datetime->setTimeStamp($end_timestamp);
            $this->datetime->add(new \DateInterval('P1D'));
            $end_timestamp = $this->datetime->getTimestamp();
            $this->endDate = gmdate(FMT_DATETIME, $end_timestamp);
            $date_set = true;
        } elseif ($begin) {
            // If not, just use whichever one was set.
            $this->date = $begin;
            $date_set = true;
        } elseif ($end) {
            $this->date = $end;
            $date_set = true;
        } elseif (isset($_REQUEST['date'])) {
            $date = $this->validateDateString($_REQUEST['date']);
            if ($date) {
                $this->date = $date;
                $date_set = true;
            }
        }

        if (!$date_set) {
            // No (valid) date specified. Look up the most recent date with results.
            $stmt = $this->db->prepare('
                SELECT starttime FROM build
                WHERE projectid = :projectid
                ORDER BY starttime DESC LIMIT 1');
            $this->db->execute($stmt, [':projectid' => $this->project->Id]);
            $starttime = $stmt->fetchColumn();
            if ($starttime) {
                $this->date = TestingDay::get($this->project, $starttime);
            }
        }

        $response['date_set'] = $date_set;

        if ($this->beginDate == self::BEGIN_EPOCH) {
            $this->setDate($this->date);
        }
    }

    public function getFilterData()
    {
        return $this->filterdata;
    }

    public function setFilterData(array $filterdata)
    {
        $this->filterdata = $filterdata;
        $this->filterSQL = $this->filterdata['sql'];
        if ($this->filterdata['limit'] > 0) {
            $this->limitSQL = ' LIMIT ' . $this->filterdata['limit'];
        }
    }

    public function getFilterSQL()
    {
        return $this->filterSQL;
    }

    // Return a flattened array of all filters and sub-filters.
    public function flattenFilters()
    {
        $filters = [];
        foreach ($this->filterdata['filters'] as $filter) {
            if (array_key_exists('filters', $filter)) {
                foreach ($filter['filters'] as $subfilter) {
                    $filters[] = $subfilter;
                }
            } else {
                $filters[] = $filter;
            }
        }
        return $filters;
    }
}

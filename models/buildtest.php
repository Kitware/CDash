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

/** Build Test class */
class buildtest
{
    public $TestId;
    public $Status;
    public $Time;
    public $TimeMean;
    public $TimeStd;
    public $TimeStatus;
    public $BuildId;

    // Insert in the database
    public function Insert()
    {
        if (!$this->BuildId) {
            add_log('BuildId is not set', 'BuildTest::Insert()', LOG_ERR, 0, 0);
            return false;
        }

        if (!$this->TestId) {
            add_log('TestId is not set', 'BuildTest::Insert()', LOG_ERR, 0, $this->BuildId);
            return false;
        }

        if (empty($this->Time)) {
            $this->Time = 0;
        }
        if (empty($this->TimeMean)) {
            $this->TimeMean = 0;
        }
        if (empty($this->TimeStd)) {
            $this->TimeStd = 0;
        }
        if (empty($this->TimeStatus)) {
            $this->TimeStatus = 0;
        }

        $query = 'INSERT INTO build2test (buildid,testid,status,time,timemean,timestd,timestatus)
                 VALUES (' . qnum($this->BuildId) . ',' . qnum($this->TestId) . ",'$this->Status'," . qnum($this->Time) . ','
            . qnum($this->TimeMean) . ',' . qnum($this->TimeStd) . ',' . qnum($this->TimeStatus) . ')';
        if (!pdo_query($query)) {
            add_last_sql_error('BuildTest:Insert', 0, $this->BuildId);
            return false;
        }
        return true;
    }

    /** Get the number of tests that are failing */
    public function GetNumberOfFailures($checktesttiming, $testtimemaxstatus)
    {
        if (!$this->BuildId) {
            echo 'BuildTest::GetNumberOfFailures(): BuildId not set';
            return false;
        }

        $sql = 'SELECT testfailed,testnotrun,testtimestatusfailed FROM build WHERE id=' . qnum($this->BuildId);
        $query = pdo_query($sql);
        if (!$query) {
            add_last_sql_error('BuildTest:GetNumberOfFailures', 0, $this->BuildId);
            return false;
        }

        $nfail_array = pdo_fetch_array($query);

        $sumerrors = 0;
        if ($nfail_array['testfailed'] > 0) {
            $sumerrors += $nfail_array['testfailed'];
        }
        if ($nfail_array['testnotrun'] > 0) {
            $sumerrors += $nfail_array['testnotrun'];
        }

        // Find if the build has any test failings
        if ($checktesttiming) {
            if ($nfail_array['testtimestatusfailed'] > 0) {
                $sumerrors += $nfail_array['testtimestatusfailed'];
            }
        }
        return $sumerrors;
    }

    public static function marshalStatus($status)
    {
        return array('passed' => array('Passed', 'normal'),
                     'failed' => array('Failed', 'error'),
                     'notrun' => array('Not Run', 'warning'))[$status];
    }

    public static function marshal($data, $buildid, $projectid, $projectshowttestime, $testtimemaxstatus, $testdate)
    {
        $marshaledData = array(
            'id' => $data['id'],
            'status' => self::marshalStatus($data['status'])[0],
            'statusclass' => self::marshalStatus($data['status'])[1],
            'name' => $data['name'],
            'execTime' => time_difference($data['time'], true, '', true),
            'execTimeFull' => floatval($data['time']),
            'details' => $data['details'],
            'summaryLink' => "testSummary.php?project=$projectid&name=" . urlencode($data['name']) . "&date=$testdate",
            'detailsLink' => "testDetails.php?test=" . $data['id'] . "&build=$buildid");

        if ($data['newstatus']) {
            $marshaledData['new'] = '1';
        }

        if ($projectshowtesttime) {
            if ($data['timestatus'] < $testtimemaxstatus) {
                $marshaledData['timestatus'] = 'Passed';
                $marshaledData['timestatusclass'] = 'normal';
            } else {
                $marshaledData['timestatus'] = 'Failed';
                $marshaledData['timestatusclass'] = 'error';
            }
        }

        if ($CDASH_DB_TYPE == 'pgsql') {
            get_labels_JSON_from_query_results(
                'SELECT text FROM label, label2test WHERE ' .
                'label.id=label2test.labelid AND ' .
                "label2test.testid=" . $marshaledData['id'] . " AND " .
                "label2test.buildid='$buildid' " .
                'ORDER BY text ASC',
                $marshaledData);
        } else {
            if (!empty($data['labels'])) {
                $labels = explode(',', $data['labels']);
                $marshaledData['labels'] = $labels;
            }
        }

        return $marshaledData;
    }
}

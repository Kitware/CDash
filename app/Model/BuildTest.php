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
namespace CDash\Model;

use CDash\Config;

/** Build Test class */
class BuildTest
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

    public static function marshalMissing($name, $buildid, $projectid, $projectshowtesttime, $testtimemaxstatus, $testdate)
    {
        $data = array();
        $data['name'] = $name;
        $data['status'] = 'missing';
        $data['id'] = '';
        $data['time'] = '';
        $data['details'] = '';
        $data["newstatus"] = false;

        $test = self::marshal($data, $buildid, $projectid, $projectshowtesttime, $testtimemaxstatus, $testdate);

        // Since these tests are missing they should
        // not behave like other tests
        $test['execTime'] = '';
        $test['summary'] = '';
        $test['detailsLink'] = '';
        $test['summaryLink'] = '';

        return $test;
    }

    public static function marshalStatus($status)
    {
        $statuses = array('passed' => array('Passed', 'normal'),
                          'failed' => array('Failed', 'error'),
                          'notrun' => array('Not Run', 'warning'),
                          'missing' => array('Missing', 'missing'));

        return $statuses[$status];
    }

    public static function marshal($data, $buildid, $projectid, $projectshowtesttime, $testtimemaxstatus, $testdate)
    {
        $marshaledStatus = self::marshalStatus($data['status']);
        if ($data['details'] === 'Disabled') {
            $marshaledStatus = array('Not Run', 'disabled-test');
        }
        $marshaledData = array(
            'id' => $data['id'],
            'buildid' => $buildid,
            'status' => $marshaledStatus[0],
            'statusclass' => $marshaledStatus[1],
            'name' => $data['name'],
            'execTime' => time_difference($data['time'], true, '', true),
            'execTimeFull' => floatval($data['time']),
            'details' => $data['details'],
            'summaryLink' => "testSummary.php?project=$projectid&name=" . urlencode($data['name']) . "&date=$testdate",
            'summary' => 'Summary', /* Default value later replaced by AJAX */
            'detailsLink' => "testDetails.php?test=" . $data['id'] . "&build=$buildid");

        if ($data['newstatus']) {
            $marshaledData['new'] = '1';
        }

        if ($projectshowtesttime) {
            if ($data['timestatus'] == 0) {
                $marshaledData['timestatus'] = 'Passed';
                $marshaledData['timestatusclass'] = 'normal';
            } elseif ($data['timestatus'] < $testtimemaxstatus) {
                $marshaledData['timestatus'] = 'Warning';
                $marshaledData['timestatusclass'] = 'warning';
            } else {
                $marshaledData['timestatus'] = 'Failed';
                $marshaledData['timestatusclass'] = 'error';
            }
        }

        $config = Config::getInstance();
        if ($config->get('CDASH_DB_TYPE') == 'pgsql' && $marshaledData['id']) {
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

        if (isset($data['subprojectid'])) {
            $marshaledData['subprojectid'] = $data['subprojectid'];
        }

        if (isset($data['subprojectname'])) {
            $marshaledData['subprojectname'] = $data['subprojectname'];
        }

        return $marshaledData;
    }
}

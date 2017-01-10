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

include_once 'models/dynamicanalysisdefect.php';
include_once 'models/label.php';

class DynamicAnalysis
{
    public $Id;
    public $Status;
    public $Checker;
    public $Name;
    public $Path;
    public $FullCommandLine;
    public $Log;
    private $Defects;
    public $BuildId;
    public $Labels;
    public $LogCompression;
    public $LogEncoding;

    /** Add a defect */
    public function AddDefect($defect)
    {
        $defect->DynamicAnalysisId = $this->Id;
        $this->Defects[] = $defect;
    }

    public function AddLabel($label)
    {
        $label->DynamicAnalysisId = $this->Id;
        $this->Labels[] = $label;
    }

    /** Find how many dynamic analysis tests were failed or notrun status */
    public function GetNumberOfErrors()
    {
        if (strlen($this->BuildId) == 0) {
            echo 'DynamicAnalysis::GetNumberOfErrors BuildId not set';
            return false;
        }

        $query = 'SELECT count(*) FROM dynamicanalysis WHERE buildid=' . qnum($this->BuildId) .
            " AND status IN ('notrun','failed')";

        $errorcount = pdo_query($query);
        $error_array = pdo_fetch_array($errorcount);
        return $error_array[0];
    }

    /** Remove all the dynamic analysis associated with a buildid */
    public function RemoveAll()
    {
        include 'config/config.php';

        if (strlen($this->BuildId) == 0) {
            echo 'DynamicAnalysis::RemoveAll BuildId not set';
            return false;
        }

        if ($CDASH_DB_TYPE == 'pgsql') {
            // postgresql doesn't support multiple delete

            $query = 'BEGIN';
            $query = 'DELETE FROM dynamicanalysisdefect WHERE dynamicanalysis.buildid=' . qnum($this->BuildId) . '
                AND dynamicanalysis.id=dynamicanalysisdefect.dynamicanalysisid';
            $query = 'DELETE FROM dynamicanalysis WHERE dynamicanalysis.buildid=' . qnum($this->BuildId);
            $query = 'COMMIT';
        } else {
            $query = 'DELETE dynamicanalysisdefect,dynamicanalysis FROM dynamicanalysisdefect, dynamicanalysis
               WHERE dynamicanalysis.buildid=' . qnum($this->BuildId) . '
               AND dynamicanalysis.id=dynamicanalysisdefect.dynamicanalysisid';
        }

        if (!pdo_query($query)) {
            add_last_sql_error('DynamicAnalysis RemoveAll', 0, $this->BuildId);
            return false;
        }

        $query = 'DELETE FROM dynamicanalysis WHERE buildid=' . qnum($this->BuildId);
        if (!pdo_query($query)) {
            add_last_sql_error('DynamicAnalysis RemoveAll', 0, $this->BuildId);
            return false;
        }
    }

    /** Insert labels */
    public function InsertLabelAssociations()
    {
        if (empty($this->Labels)) {
            return;
        }

        if ($this->Id) {
            foreach ($this->Labels as $label) {
                $label->DynamicAnalysisId = $this->Id;
                $label->Insert();
            }
        } else {
            add_log('No DynamicAnalysis::Id - cannot call $label->Insert...',
                'DynamicAnalysis::InsertLabelAssociations', LOG_ERR,
                0, $this->BuildId, CDASH_OBJECT_DYNAMICANALYSIS, $this->Id);
        }
    }

    // Insert the DynamicAnalysis
    public function Insert()
    {
        if (strlen($this->BuildId) == 0) {
            echo 'DynamicAnalysis::Insert BuildId not set';
            return false;
        }

        $id = '';
        $idvalue = '';
        if ($this->Id) {
            $id = 'id,';
            $idvalue = qnum($this->Id) . ',';
        }

        // Handle log decoding/decompression
        if (strtolower($this->LogEncoding) == 'base64') {
            $this->Log = str_replace(array("\r\n", "\n", "\r"), '', $this->Log);
            $this->Log = base64_decode($this->Log);
        }
        if (strtolower($this->LogCompression) == 'gzip') {
            $this->Log = gzuncompress($this->Log);
        }
        if ($this->Log === false) {
            add_log('Unable to decompress dynamic analysis log',
                'DynamicAnalysis::Insert', LOG_ERR, 0, $this->BuildId, CDASH_OBJECT_DYNAMICANALYSIS, $this->Id);
            $this->Log = '';
        }

        // Only store 1MB of log.
        if (strlen($this->Log) > 1024 * 1024) {
            $truncated_msg = "\n(truncated)\n";
            $keep_length = (1024 * 1024) - strlen($truncated_msg);
            $this->Log = substr($this->Log, 0, $keep_length);
            $this->Log .= $truncated_msg;
        }

        $this->Status = pdo_real_escape_string($this->Status);
        $this->Checker = pdo_real_escape_string($this->Checker);
        $this->Name = pdo_real_escape_string($this->Name);
        $path = pdo_real_escape_string(substr($this->Path, 0, 255));
        $fullCommandLine = pdo_real_escape_string(substr($this->FullCommandLine, 0, 255));
        $this->Log = pdo_real_escape_string($this->Log);
        $this->BuildId = pdo_real_escape_string($this->BuildId);

        $query = 'INSERT INTO dynamicanalysis (' . $id . 'buildid,status,checker,name,path,fullcommandline,log)
              VALUES (' . $idvalue . qnum($this->BuildId) . ",'$this->Status','$this->Checker','$this->Name','" . $path . "',
                      '" . $fullCommandLine . "','$this->Log')";
        if (!pdo_query($query)) {
            add_last_sql_error('DynamicAnalysis Insert', 0, $this->BuildId);
            return false;
        }

        if (!$this->Id) {
            $this->Id = pdo_insert_id('dynamicanalysis');
        }

        // Add the defects
        if (!empty($this->Defects)) {
            foreach ($this->Defects as $defect) {
                $defect->DynamicAnalysisId = $this->Id;
                $defect->Insert();
            }
        }

        // Add the labels
        $this->InsertLabelAssociations();
        return true;
    }
}

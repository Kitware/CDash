<?php
/*=========================================================================

  Program:   CDash - Cross-Platform Dashboard System
  Module:    $Id$
  Language:  PHP
  Date:      $Date$
  Version:   $Revision$

  Copyright (c) 2002 Kitware, Inc.  All rights reserved.
  See Copyright.txt or http://www.cmake.org/HTML/Copyright.html for details.

  This software is distributed WITHOUT ANY WARRANTY; without even
  the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
  PURPOSE.  See the above copyright notices for more information.

  =========================================================================*/
class coveragefilelog
{
    public $BuildId;
    public $FileId;
    public $Lines;
    public $Branches;


    public function __construct()
    {
        $this->Lines = array();
        $this->Branches = array();
    }

    public function AddLine($number, $code)
    {
        if (array_key_exists($number, $this->Lines)) {
            $this->Lines[$number] += $code;
        } else {
            $this->Lines[$number] = $code;
        }
    }

    public function AddBranch($number, $covered, $total)
    {
        $this->Branches[$number] = "$covered/$total";
    }

    /** Update the content of the file */
    public function Insert($append=false)
    {
        if (!$this->BuildId || !is_numeric($this->BuildId)) {
            add_log("BuildId not set", "CoverageFileLog::Insert()", LOG_ERR,
                    0, $this->BuildId, CDASH_OBJECT_COVERAGE, $this->FileId);
            return false;
        }

        if (!$this->FileId || !is_numeric($this->FileId)) {
            add_log("FileId not set", "CoverageFileLog::Insert()", LOG_ERR,
                    0, $this->BuildId, CDASH_OBJECT_COVERAGE, $this->FileId);
            return false;
        }

        pdo_begin_transaction();
        $update = false;
        if ($append) {
            // Load any previously existing results for this file & build.
            $update = $this->Load(true);
        }

        $log = '';
        foreach ($this->Lines as $lineNumber=>$code) {
            $log .= $lineNumber.':'.$code.';';
        }
        foreach ($this->Branches as $lineNumber => $code) {
            $log .= 'b' . $lineNumber . ':' . $code . ';';
        }

        if ($log != '') {
            if ($update) {
                $sql_command = "UPDATE";
                $sql = "UPDATE coveragefilelog SET log='$log'
                WHERE buildid=".qnum($this->BuildId)." AND
                fileid=".qnum($this->FileId);
            } else {
                $sql_command = "INSERT";
                $sql = "INSERT INTO coveragefilelog (buildid,fileid,log) VALUES ";
                $sql.= "(".qnum($this->BuildId).",".qnum($this->FileId).",'".$log."')";
            }
            pdo_query($sql);
            add_last_sql_error("CoverageFileLog::$sql_command()");
        }
        pdo_commit();
        return true;
    }

    public function Load($for_update = false)
    {
        global $CDASH_DB_TYPE;

        $query = "SELECT log FROM coveragefilelog
            WHERE fileid=".qnum($this->FileId)."
            AND buildid=".qnum($this->BuildId);
        if ($for_update) {
            $query .= " FOR UPDATE";
        }

        $result = pdo_query($query);
        if (!$result || pdo_num_rows($result) < 1) {
            return false;
        }

        $row = pdo_fetch_array($result);
        if ($CDASH_DB_TYPE == 'pgsql') {
            $log = stream_get_contents($row['log']);
        } else {
            $log = $row['log'];
        }

        $log_entries = explode(';', $log);
        foreach ($log_entries as $log_entry) {
            if (empty($log_entry)) {
                continue;
            }
            list($line, $value) = explode(':', $log_entry);
            if ($line[0] === 'b') {
                // Branch coverage
                $line = ltrim($line, 'b');
                list($covered, $total) = explode('/', $value);
                $this->AddBranch($line, $covered, $total);
            } else {
                // Line coverage
                $this->AddLine($line, $value);
            }
        }
        return true;
    }

    public function GetStats()
    {
        $stats = array();
        $stats['loctested'] = 0;
        $stats['locuntested'] = 0;
        $stats['branchestested'] = 0;
        $stats['branchesuntested'] = 0;
        foreach ($this->Lines as $line => $timesHit) {
            if ($timesHit > 0) {
                $stats['loctested'] += 1;
            } else {
                $stats['locuntested'] += 1;
            }
        }

        foreach ($this->Branches as $line => $value) {
            list($timesHit, $total) = explode('/', $value);
            if ($timesHit > 0) {
                $stats['branchestested'] += 1;
            } else {
                $stats['branchesuntested'] += 1;
            }
        }

        return $stats;
    }
}

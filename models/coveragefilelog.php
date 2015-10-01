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
  public function Insert()
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

      $log = '';
      foreach ($this->Lines as $lineNumber=>$code) {
          $log .= $lineNumber.':'.$code.';';
      }
      foreach ($this->Branches as $lineNumber => $code) {
          $log .= 'b' . $lineNumber . ':' . $code . ';';
      }

      if ($log != '') {
          $sql = "INSERT INTO coveragefilelog (buildid,fileid,log) VALUES ";
          $sql.= "(".qnum($this->BuildId).",".qnum($this->FileId).",'".$log."')";
          pdo_query($sql);
          add_last_sql_error("CoverageFileLog::Insert()");
      }
      return true;
  }
}

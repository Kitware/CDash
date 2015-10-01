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
class dynamicanalysisdefect
{
    public $DynamicAnalysisId;
    public $Type;
    public $Value;
  
  // Insert the DynamicAnalysisDefect
  public function Insert()
  {
      if (strlen($this->DynamicAnalysisId)==0) {
          echo "DynamicAnalysisDefect::Insert DynamicAnalysisId not set";
          return false;
      }

      $this->Type = pdo_real_escape_string($this->Type);
      $this->Value = pdo_real_escape_string($this->Value);
      $this->DynamicAnalysisId = pdo_real_escape_string($this->DynamicAnalysisId);
    
      $query = "INSERT INTO dynamicanalysisdefect (dynamicanalysisid,type,value)
              VALUES (".qnum($this->DynamicAnalysisId).",'$this->Type','$this->Value')";
      if (!pdo_query($query)) {
          add_last_sql_error("DynamicAnalysisDefect Insert");
          return false;
      }
      return true;
  } // end function insert
}

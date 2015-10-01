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
class coveragesummarydiff
{
    public $LocTested;
    public $LocUntested;
    public $BuildId;
  
    public function Insert()
    {
        pdo_query("INSERT INTO coveragesummarydiff (buildid,loctested,locuntested) 
              VALUES(".qnum($this->BuildId).",".qnum($this->LocTested).",".qnum($this->LocUntested).")");
        add_last_sql_error("CoverageSummary:ComputeDifference");
    }
}

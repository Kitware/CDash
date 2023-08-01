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

use CDash\Database;

class DynamicAnalysisDefect
{
    public $DynamicAnalysisId;
    public $Type;
    public $Value;

    // Insert the DynamicAnalysisDefect
    public function Insert(): bool
    {
        if (strlen($this->DynamicAnalysisId) == 0) {
            abort(500, 'DynamicAnalysisDefect::Insert DynamicAnalysisId not set');
        }

        $db = Database::getInstance();

        $query = $db->executePrepared('
                     INSERT INTO dynamicanalysisdefect (dynamicanalysisid, type, value)
                     VALUES (?, ?, ?)
                 ', [intval($this->DynamicAnalysisId), $this->Type ?? '', $this->Value ?? '']);
        if ($query === false) {
            add_last_sql_error('DynamicAnalysisDefect Insert');
            return false;
        }
        return true;
    }
}

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

use App\Models\Build;
use App\Models\Label as EloquentLabel;
use App\Models\RichBuildAlert;
use App\Models\Test;

/** Label */
class Label
{
    public $Id;
    public string $Text = '';

    public $BuildId;
    public $BuildFailureId;
    public ?Test $Test = null;

    /**
     * Save in the database
     */
    public function Insert()
    {
        $this->Id = EloquentLabel::firstOrCreate(['text' => $this->Text ?? ''])->id;

        // Insert relationship records, too, but only for those relationships
        // established by callers. (If coming from test.php, for example, TestId
        // will be set, but none of the others will. Similarly for other callers.)

        if (!empty($this->BuildId)) {
            Build::findOrFail((int) $this->BuildId)->labels()->syncWithoutDetaching([$this->Id]);
        }

        if (!empty($this->BuildFailureId)) {
            RichBuildAlert::findOrFail((int) $this->BuildFailureId)->labels()->syncWithoutDetaching([$this->Id]);
        }

        $this->Test?->labels()->syncWithoutDetaching([$this->Id]);

        return true;
    }
}

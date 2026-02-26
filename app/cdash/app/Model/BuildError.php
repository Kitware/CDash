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

use App\Models\BasicBuildAlert;
use Illuminate\Support\Str;

/** BuildError */
class BuildError
{
    public $Type;
    public $LogLine;
    public $Text;
    public $SourceFile;
    public $SourceLine;
    public $PreContext;
    public $PostContext;
    public $RepeatCount;
    public $BuildId;

    // Insert in the database (no update possible)
    public function Insert(): void
    {
        if (!$this->BuildId) {
            abort(500, 'BuildError::Insert(): BuildId not set.');
        }

        if (empty($this->SourceLine)) {
            $this->SourceLine = 0;
        }
        if (empty($this->RepeatCount)) {
            $this->RepeatCount = 0;
        }

        BasicBuildAlert::create([
            'buildid' => (int) $this->BuildId,
            'type' => $this->Type,
            'logline' => (int) $this->LogLine,
            'sourcefile' => $this->SourceFile ?? '',
            'sourceline' => (int) $this->SourceLine,
            'repeatcount' => (int) $this->RepeatCount,
            'newstatus' => 0,
            'stdoutput' => $this->PreContext . Str::rtrim($this->Text) . PHP_EOL . $this->PostContext,
            'stderror' => $this->Text,
        ]);
    }

    /**
     * Returns a self referencing URI for the current BuildError.
     */
    public function GetUrlForSelf(): string
    {
        return url('/builds/' . $this->BuildId . '/errors');
    }
}

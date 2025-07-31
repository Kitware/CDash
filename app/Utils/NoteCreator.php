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

namespace App\Utils;

use App\Models\Note;

/**
 * This class is responsible for creating the various models associated
 * with a build note.
 **/
class NoteCreator
{
    public $buildid;
    public $name;
    public $time;
    public $text;

    public function __construct()
    {
        $this->buildid = null;
        $this->name = '';
        $this->time = '';
        $this->text = '';
    }

    /**
     * Record this note in the database.
     **/
    public function create(): void
    {
        // Create the note if it doesn't already exist.
        $note = Note::firstOrCreate([
            'name' => $this->name,
            'text' => $this->text,
        ]);

        // Create the build2note record.
        $note->builds()->attach((int) $this->buildid, ['time' => $this->time]);
    }
}

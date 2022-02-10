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

namespace App\Services;

use App\Models\BuildNote;
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

    private $crc32;

    public function __construct()
    {
        $this->buildid = null;
        $this->name = '';
        $this->time = '';
        $this->text = '';
    }

    /** Get the CRC32 */
    public function computeCrc32()
    {
        if (strlen($this->crc32) > 0) {
            return $this->crc32;
        }
        // Compute the CRC32 for the note.
        $text = pdo_real_escape_string($this->text);
        $name = pdo_real_escape_string($this->name);
        $this->crc32 = crc32($text . $name);
        return $this->crc32;
    }

    /**
     * Record this note in the database.
     **/
    public function create()
    {
        // Create the note if it doesn't already exist.
        $this->computeCrc32();
        $note = Note::where('crc32', '=', $this->crc32)->first();
        if (!$note) {
            $note = new Note;
            $note->name = $this->name;
            $note->text = $this->text;
            $note->crc32 = $this->crc32;
            $note->save();
        }

        // Create the build2note record.
        $buildnote = new BuildNote;
        $buildnote->buildid = $this->buildid;
        $buildnote->noteid = $note->id;
        $buildnote->time = $this->time;
        $buildnote->save();
    }
}

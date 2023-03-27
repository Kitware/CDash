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

/** BuildFile */
class BuildFile
{
    public $Type;
    public $Filename;
    public $md5;
    public $BuildId;

    // Insert in the database (no update possible)
    public function Insert()
    {
        if (!$this->BuildId) {
            \Log::warning('BuildId not set');
            return false;
        }

        if (!$this->Type) {
            \Log::warning('Type not set');
            return false;
        }

        if (!$this->md5) {
            \Log::warning('md5 not set');
            return false;
        }

        if (!$this->Filename) {
            \Log::warning('Filename not set');
            return false;
        }

        $filename = pdo_real_escape_string($this->Filename);
        $type = pdo_real_escape_string($this->Type);
        $md5 = pdo_real_escape_string($this->md5);

        // Check if we already have a row
        $existing_row =
            \DB::table('buildfile')
            ->where('buildid', $this->BuildId)
            ->where('md5', $this->md5)
            ->first();
        if ($existing_row) {
            return false;
        }

        return \DB::table('buildfile')
            ->insert([
                    'buildid' => $this->BuildId,
                    'type' => $this->Type,
                    'filename' => $this->Filename,
                    'md5' => $this->md5,
            ]);
    }

    // Returns the buildid associated with this file's MD5 if it has been
    // uploaded previously, false otherwise.
    public function MD5Exists()
    {
        // Check if we already have a row
        $existing_row =
            \DB::table('buildfile')
            ->where('md5', $this->md5)
            ->first();
        if (!$existing_row) {
            return false;
        }
        return $existing_row->buildid;
    }

    /** Delete this BuildFile */
    public function Delete()
    {
        if (!$this->BuildId || !$this->md5) {
            return false;
        }
        \DB::table('buildfile')
        ->where('buildid', $this->BuildId)
        ->where('md5', $this->md5)
        ->delete();
    }
}

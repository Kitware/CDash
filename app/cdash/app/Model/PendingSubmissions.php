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

use Illuminate\Support\Facades\Log;

/** PendingSubmission class */
class PendingSubmissions
{
    public ?Build $Build = null;
    public int $NumFiles = 0;
    public int $Recheck = 0; // TODO: convert to boolean
    private bool $Filled = false;

    /** Return true if a record already exists for this build. */
    public function Exists(): bool
    {
        if ($this->Build === null) {
            return false;
        }

        return \App\Models\PendingSubmissions::where('buildid', $this->Build->Id)->exists();
    }

    /** Insert a new record in the database or update an existing one. */
    public function Save(): void
    {
        if ($this->Build === null) {
            Log::error('Build not set', [
                'function' => 'PendingSubmission::Save',
            ]);
            return;
        }

        \App\Models\PendingSubmissions::upsert([
            'buildid' => $this->Build->Id,
            'numfiles' => $this->NumFiles,
            'recheck' => $this->Recheck,
        ], 'buildid');
    }

    /** Delete this record from the database. */
    public function Delete(): void
    {
        if ($this->Build === null) {
            Log::error('Build not set', [
                'function' => 'PendingSubmission::Delete',
            ]);
            return;
        }
        if (!$this->Exists()) {
            Log::error('Record does not exist', [
                'function' => 'PendingSubmission::Delete',
            ]);
            return;
        }

        \App\Models\Build::findOrFail((int) $this->Build->Id)->pendingSubmissions()->delete();
    }

    protected function Fill(): void
    {
        if ($this->Filled) {
            return;
        }
        if ($this->Build === null) {
            Log::error('Build not set', [
                'function' => 'PendingSubmission::Fill',
            ]);
            return;
        }

        $model = \App\Models\Build::findOrFail((int) $this->Build->Id)->pendingSubmissions()->first();
        if ($model !== null) {
            $this->NumFiles = $model->numfiles;
            $this->Recheck = $model->recheck;
        }
        $this->Filled = true;
    }

    /** Get number of pending submissions for a given build. */
    public function GetNumFiles(): int
    {
        $this->Filled = false;
        $this->Fill();
        return $this->NumFiles;
    }

    public function Increment(): void
    {
        \App\Models\PendingSubmissions::where('buildid', $this->Build->Id ?? -1)->increment('numfiles');
    }

    public function Decrement(): void
    {
        \App\Models\PendingSubmissions::where('buildid', $this->Build->Id ?? -1)->decrement('numfiles');
    }

    protected function MarkForRecheck(): void
    {
        \App\Models\PendingSubmissions::where('buildid', $this->Build->Id ?? -1)->update([
            'recheck' => 1,
        ]);
    }

    public static function GetModelForBuildId($buildid): PendingSubmissions
    {
        $build = new Build();
        $build->Id = $buildid;
        $pendingSubmissions = new PendingSubmissions();
        $pendingSubmissions->Build = $build;
        return $pendingSubmissions;
    }

    public static function RecheckForBuildId($buildid): void
    {
        $pendingSubmissions = self::GetModelForBuildId($buildid);
        $pendingSubmissions->MarkForRecheck();
    }

    public static function IncrementForBuildId($buildid): void
    {
        $pendingSubmissions = self::GetModelForBuildId($buildid);
        $pendingSubmissions->Increment();
    }
}

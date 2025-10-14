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
use App\Models\BuildUpdate as EloquentBuildUpdate;
use App\Models\BuildUpdateFile;
use Illuminate\Support\Facades\DB;

class BuildUpdate
{
    /** @var array<BuildUpdateFile> */
    private array $Files = [];
    public $StartTime;
    public $EndTime;
    public $Command = '';
    public $Type;
    public $Status;
    public $Revision;
    public $PriorRevision;
    public $Path;
    public $BuildId;
    public $Append = false;
    public $UpdateId;
    public $Errors;

    public function AddFile(BuildUpdateFile $file): void
    {
        $this->Files[] = $file;
    }

    // Insert the update
    public function Insert(): int|bool
    {
        if (strlen($this->BuildId) == 0 || !is_numeric($this->BuildId)) {
            abort(500, 'BuildUpdate:Insert BuildId not set');
        }

        // Avoid a race condition when parallel processing.
        return DB::transaction(function () {
            $build = Build::findOrFail((int) $this->BuildId);

            // Check if this update already exists.
            $update = $build->updates()->first();
            $exists = false;
            if ($update !== null) {
                $exists = true;
                $this->UpdateId = $update->id;
            }

            // Remove previous updates
            if ($exists && !$this->Append) {
                // Parent builds share updates with their children.
                // So if this is a parent build remove any build2update rows
                // from the children here.
                /** @var Build $child_build */
                foreach ($build->children as $child_build) {
                    $child_build->updates()->detach();
                }

                // If the buildupdate and updatefile are not shared
                // we delete them as well.
                if ($update->builds()->count() === 1) {
                    $update->delete();
                }
                $build->updates()->detach();
                $exists = false;
                $this->UpdateId = '';
            }

            if (strlen($this->Type ?? '') > 4) {
                $this->Type = 'NA';
            }

            $nfiles = count($this->Files);
            $nwarnings = 0;
            foreach ($this->Files as $file) {
                if ($file->author === 'Local User' && $file->revision == -1) {
                    $nwarnings++;
                }
            }

            if (!$exists) {
                // TODO: Make some of these columns nullable...
                $update_model = EloquentBuildUpdate::create([
                    'starttime' => $this->StartTime ?? '',
                    'endtime' => $this->EndTime ?? '',
                    'command' => $this->Command ?? '',
                    'type' => $this->Type ?? '',
                    'status' => $this->Status ?? '',
                    'nfiles' => $nfiles,
                    'warnings' => $nwarnings,
                    'revision' => $this->Revision ?? '',
                    'priorrevision' => $this->PriorRevision ?? '',
                    'path' => $this->Path ?? '',
                ]);
                $update_model->builds()->attach((int) $this->BuildId);
                $this->UpdateId = $update_model->id;

                // If this is a parent build, make sure that all of its children
                // are also associated with a buildupdate.
                $children_needing_buildupdates = $build->children()->whereDoesntHave('updates')->pluck('id');
                $update_model->builds()->attach($children_needing_buildupdates);
            } else {
                $update_model = EloquentBuildUpdate::findOrFail((int) $this->UpdateId);
                $update_model->update([
                    'endtime' => $this->EndTime,
                    'status' => $this->Status,
                    'command' => $update_model->command . $this->Command,
                    'nfiles' => $nfiles + $update_model->nfiles,
                    'warnings' => $nwarnings + $update_model->warnings,
                ]);
            }

            foreach ($this->Files as $file) {
                $update_model->updateFiles()->save($file);
            }

            return true;
        }, 5);
    }

    /** Associate a buildupdate to a build. */
    public function AssociateBuild(int $siteid, string $name, $stamp): bool
    {
        if (!$this->BuildId) {
            abort(500, 'BuildUpdate::AssociateBuild(): BuildId not set');
        }

        $build = Build::find((int) $this->BuildId);
        if ($build === null) {
            return false;
        }

        // If we already have something in the database we return
        if ($build->updates()->exists()) {
            return true;
        }

        // Find the update id from a similar build
        $similar_build = Build::where([
            'stamp' => $stamp,
            'name' => $name,
            'siteid' => $siteid,
        ])->whereNot('id', (int) $this->BuildId)->whereHas('updates')->first();

        if ($similar_build === null) {
            return true;
        }

        $this->UpdateId = $similar_build->updates()->firstOrFail()->id;

        $build->updates()->attach($this->UpdateId);

        // check if this build's parent also needs to be associated with
        // this update.
        $build->parent?->updates()->syncWithoutDetaching((int) $this->UpdateId);
        return true;
    }

    /** Update a child build so that it shares the parent's updates.
     *  This function does not change the data model unless the parent
     * has an update and the child does not. **/
    public static function AssignUpdateToChild(int $childid, int $parentid): void
    {
        $childBuild = Build::findOrFail($childid);
        $parentBuild = Build::findOrFail($parentid);

        // Make sure the child does not already have an update.
        if ($childBuild->updates()->exists()) {
            return;
        }

        // Get the parent's update.
        $updateid = $parentBuild->updates()->first()?->id;
        if ($updateid === null) {
            return;
        }

        // Assign the parent's update to the child.
        $childBuild->updates()->attach($updateid);
    }
}

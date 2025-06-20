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
use CDash\Database;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BuildUpdate
{
    private $Files;
    public $StartTime;
    public $EndTime;
    public $Command;
    public $Type;
    public $Status;
    public $Revision;
    public $PriorRevision;
    public $Path;
    public $BuildId;
    public $Append;
    public $UpdateId;
    public $Errors;
    private $PDO;

    public function __construct()
    {
        $this->Files = [];
        $this->Command = '';
        $this->Append = false;
        $this->PDO = Database::getInstance()->getPdo();
    }

    public function AddFile($file): void
    {
        $this->Files[] = $file;
    }

    public function GetFiles(): array
    {
        return $this->Files;
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
                DB::table('build2update')
                    ->where('buildid', $this->BuildId)
                    ->delete();
                $exists = false;
                $this->UpdateId = '';
            }

            if (strlen($this->Type ?? '') > 4) {
                $this->Type = 'NA';
            }

            $nfiles = count($this->Files);
            $nwarnings = 0;
            foreach ($this->Files as $file) {
                if ($file->Author === 'Local User' && $file->Revision == -1) {
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
                $file->UpdateId = $this->UpdateId;
                $file->Insert();
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

    public function FillFromBuildId(): bool
    {
        if (!$this->BuildId) {
            return false;
        }

        $buildUpdate = Build::findOrFail((int) $this->BuildId)->updates()->first();
        if ($buildUpdate === null) {
            return false;
        }

        $this->UpdateId = $buildUpdate->id;
        $this->StartTime = $buildUpdate->starttime;
        $this->EndTime = $buildUpdate->endtime;
        $this->Command = $buildUpdate->command;
        $this->Type = $buildUpdate->type;
        $this->Status = $buildUpdate->status;
        $this->Revision = $buildUpdate->revision;
        $this->PriorRevision = $buildUpdate->priorrevision;
        $this->Path = $buildUpdate->path;

        // Get updated files too.
        $stmt = $this->PDO->prepare(
            'SELECT uf.* FROM updatefile uf
            JOIN build2update b2u ON uf.updateid = b2u.updateid
            WHERE b2u.buildid = ?');
        pdo_execute($stmt, [$this->BuildId]);
        while ($row = $stmt->fetch()) {
            $file = new BuildUpdateFile();
            $file->Filename = $row['filename'];
            $file->CheckinDate = $row['checkindate'];
            $file->Author = $row['author'];
            $file->Email = $row['email'];
            $file->Committer = $row['committer'];
            $file->CommitterEmail = $row['committeremail'];
            $file->Log = $row['log'];
            $file->Revision = $row['revision'];
            $file->PriorRevision = $row['priorrevision'];
            $file->Status = $row['status'];
            $file->UpdateId = $row['updateid'];
            $this->AddFile($file);
        }

        usort($this->Files, fn ($file1, $file2) => Str::afterLast('/', $file1->Filename) <=> Str::afterLast('/', $file2->Filename));

        return true;
    }
}

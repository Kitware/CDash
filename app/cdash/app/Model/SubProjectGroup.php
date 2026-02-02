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

use App\Models\Project;
use App\Models\SubProjectGroup as EloquentSubProjectGroup;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class SubProjectGroup
{
    private int $Id = 0;
    private int $ProjectId = 0;
    private string $Name = '';
    private int $IsDefault = 0;
    private ?int $CoverageThreshold = null;
    private int $Position = 1;

    /** Get the Id of this subproject group. */
    public function GetId(): int
    {
        return $this->Id;
    }

    /** Set the id of this subproject group.  This function loads the
     * rest of the details about this group from the database.
     **/
    public function SetId(int $id): void
    {
        $this->Id = $id;

        $model = EloquentSubProjectGroup::findOrFail($id);

        $this->Name = $model->name;
        $this->ProjectId = $model->projectid;
        $this->CoverageThreshold = $model->coveragetheshold;
        $this->IsDefault = $model->is_default;
        $this->Position = $model->position;
    }

    /** Function to set the project id. */
    public function SetProjectId(int $projectid): void
    {
        $this->ProjectId = $projectid;
        if ($this->Name !== '') {
            $this->Fill();
        }
    }

    /** Get the Name of this subproject group. */
    public function GetName(): string
    {
        if (strlen($this->Name) > 0) {
            return $this->Name;
        }

        if ($this->Id < 1) {
            abort(500, 'SubProjectGroup GetName(): Id not set');
        }

        // Also fills the other fields.
        $this->SetId($this->Id);

        return $this->Name;
    }

    /** Set the Name of the subproject. */
    public function SetName(string $name): void
    {
        $this->Name = $name;
        if ($this->ProjectId > 0) {
            $this->Fill();
        }
    }

    /**
     * Get whether or not this subproject group is the default group.
     *
     * TODO: (williamjallen) why does this function return an int?  It should return a bool...
     */
    public function GetIsDefault(): int
    {
        return $this->IsDefault;
    }

    /** Set whether or not this subproject group is the default group. */
    public function SetIsDefault(int $is_default): void
    {
        if ($is_default) {
            $this->IsDefault = 1;
        } else {
            $this->IsDefault = 0;
        }
    }

    /** Get the coverage threshold for this subproject group. */
    public function GetCoverageThreshold(): ?int
    {
        return $this->CoverageThreshold;
    }

    /** Set the coverage threshold for this subproject group. */
    public function SetCoverageThreshold(int $threshold): void
    {
        $this->CoverageThreshold = $threshold;
    }

    /** Populate the ivars of an existing subproject group.
     * Called automatically once name & projectid are set.
     **/
    protected function Fill(): void
    {
        if ($this->Name === '' || $this->ProjectId === 0) {
            Log::warning("Name='" . $this->Name . "' or ProjectId='" . $this->ProjectId . "' not set", [
                'function' => 'SubProjectGroup::Fill',
            ]);
            return;
        }

        $model = EloquentSubProjectGroup::where([
            'projectid' => $this->ProjectId,
            'name' => $this->Name,
            'endtime' => '1980-01-01 00:00:00',
        ])->first();

        if ($model === null) {
            return;
        }

        $this->Id = $model->id;
        $this->CoverageThreshold = $model->coveragetheshold;
        $this->IsDefault = $model->is_default;
    }

    /** Delete a subproject group */
    public function Delete(bool $keephistory = true): void
    {
        if ($this->Id < 1) {
            return;
        }

        $eloquent_model = EloquentSubProjectGroup::findOrFail($this->Id);

        // If there are no subprojects in this group we can safely remove it.
        if ($eloquent_model->subProjects()->count() === 0) {
            $keephistory = false;
        }

        if (!$keephistory) {
            $eloquent_model->delete();
        } else {
            $eloquent_model->update([
                'endtime' => Carbon::now(),
            ]);
        }
    }

    /** Return if a subproject group exists */
    protected function Exists(): bool
    {
        if ($this->Id < 1) {
            return false;
        }

        return EloquentSubProjectGroup::where([
            'id' => $this->Id,
            'endtime' => '1980-01-01 00:00:00',
        ])->exists();
    }

    /** Save this subproject group in the database. */
    public function Save(): void
    {
        if ($this->Name === '' || $this->ProjectId === 0) {
            Log::warning("Name='" . $this->Name . "' or ProjectId='" . $this->ProjectId . "' not set", [
                'function' => 'SubProjectGroup::Save',
            ]);
            return;
        }

        $project = Project::findOrFail($this->ProjectId);

        // Load the default coverage threshold for this project if one
        // hasn't been set for this group.
        if (!isset($this->CoverageThreshold)) {
            $this->CoverageThreshold = $project->coveragethreshold;
        }

        // Force is_default=1 if this will be the first subproject group
        // for this project.
        if ($project->subProjectGroups()->count() === 0) {
            $this->IsDefault = 1;
        }

        // Trim the name
        $this->Name = trim($this->Name);

        $model = EloquentSubProjectGroup::find($this->Id);

        // Check if the group already exists.
        if ($model !== null) {
            // Update the group
            $model->update([
                'name' => $this->Name,
                'projectid' => $this->ProjectId,
                'is_default' => $this->IsDefault,
                'coveragethreshold' => $this->CoverageThreshold,
            ]);
        } else {
            // insert the subproject

            // Double check that it's not already in the database.
            $model = EloquentSubProjectGroup::where([
                'name' => $this->Name,
                'projectid' => $this->ProjectId,
                'endtime' => '1980-01-01 00:00:00',
            ])->first();
            if ($model !== null) {
                $this->Id = $model->id;
                return;
            }

            $this->Id = EloquentSubProjectGroup::create([
                'name' => $this->Name,
                'projectid' => $this->ProjectId,
                'is_default' => $this->IsDefault,
                'coveragethreshold' => $this->CoverageThreshold,
                'starttime' => Carbon::now(),
                'endtime' => '1980-01-01 00:00:00',
                'position' => $this->GetNextPosition(),
            ])->id;
        }

        // Make sure there's only one default group per project.
        if ($this->IsDefault) {
            EloquentSubProjectGroup::where('projectid', $this->ProjectId)
                ->where('id', '!=', $this->Id)
                ->update([
                    'is_default' => 0,
                ]);
        }
    }

    public function GetPosition(): int
    {
        return $this->Position;
    }

    /** Get the next position available for this group. */
    protected function GetNextPosition(): int
    {
        $model = EloquentSubProjectGroup::where([
            'projectid' => $this->ProjectId,
            'endtime' => '1980-01-01 00:00:00',
        ])->orderBy('position', 'desc')->first();

        return $model !== null ? $model->position + 1 : 1;
    }
}

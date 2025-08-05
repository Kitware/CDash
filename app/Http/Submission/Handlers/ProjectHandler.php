<?php

namespace App\Http\Submission\Handlers;

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

use CDash\Model\Label;
use CDash\Model\Project;
use CDash\Model\SubProject;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProjectHandler extends AbstractXmlHandler
{
    private $SubProject;
    private $SubProjectPosition;
    private $Dependencies; // keep an array of dependencies in order to remove them
    private $SubProjects; // keep an array of subprojects in order to remove them
    private $CurrentDependencies; // The dependencies of the current SubProject.
    private $ProjectNameMatches;
    protected static ?string $schema_file = '/app/Validators/Schemas/Project.xsd';

    /** Constructor */
    public function __construct(Project $project)
    {
        parent::__construct($project);

        // Only actually track stuff and write it into the database if the
        // Project.xml file's name element matches this project's name in the
        // database.
        //
        $this->ProjectNameMatches = true;

        $this->SubProjectPosition = 1;
    }

    /** startElement function */
    public function startElement($parser, $name, $attributes): void
    {
        parent::startElement($parser, $name, $attributes);

        // Check that the project name matches
        if ($name == 'PROJECT') {
            if (get_project_id($attributes['NAME']) != $this->GetProject()->Id) {
                Log::error('Wrong project name: ' . $attributes['NAME'], [
                    'function' => 'ProjectHandler::startElement',
                    'projectid' => $this->GetProject()->Id,
                ]);
                $this->ProjectNameMatches = false;
            }
        }

        if (!$this->ProjectNameMatches) {
            return;
        }

        if ($name == 'PROJECT') {
            $this->SubProjects = [];
            $this->Dependencies = [];
        } elseif ($name == 'SUBPROJECT') {
            $this->CurrentDependencies = [];
            $this->SubProject = new SubProject();
            $this->SubProject->SetProjectId($this->GetProject()->Id);
            $this->SubProject->SetName($attributes['NAME']);
            if (array_key_exists('GROUP', $attributes)) {
                $this->SubProject->SetGroup($attributes['GROUP']);
            }
        } elseif ($name == 'DEPENDENCY') {
            // A DEPENDENCY is expected to be:
            //
            //  - another subproject that already exists
            //    (from a previous element in this submission)
            //
            $dependentProject = new SubProject();
            $dependentProject->SetName($attributes['NAME']);
            $dependentProject->SetProjectId($this->GetProject()->Id);
            // The subproject's Id is automatically loaded once its name & projectid
            // are set.
            $this->CurrentDependencies[] = $dependentProject->GetId();
        }
    }

    /** endElement function */
    public function endElement($parser, $name): void
    {
        parent::endElement($parser, $name);

        if (!$this->ProjectNameMatches) {
            return;
        }

        if ($name == 'PROJECT') {
            foreach ($this->SubProjects as $subproject) {
                if (config('cdash.delete_old_subprojects')) {
                    // Remove dependencies that do not exist anymore,
                    // but only for those relationships where both sides
                    // are present in $this->SubProjects.
                    $dependencyids = $subproject->GetDependencies();
                    $removeids = array_diff($dependencyids, $this->Dependencies[$subproject->GetId()]);

                    foreach ($removeids as $removeid) {
                        if (array_key_exists($removeid, $this->SubProjects)) {
                            $subproject->RemoveDependency(intval($removeid));
                        } else {
                            // TODO: (williamjallen) Rewrite this loop to not make repetitive queries
                            $dep = DB::select('SELECT name FROM subproject WHERE id=?', [intval($removeid)])[0] ?? [];
                            $dep = $dep !== [] ? $dep->name : intval($removeid);
                            Log::warning("Not removing dependency $dep($removeid) from " . $subproject->GetName() .
                                ' because it is not a SubProject element in this Project.xml file', [
                                    'function' => 'ProjectHandler:endElement',
                                    'projectid' => $this->GetProject()->Id,
                                ]);
                        }
                    }
                }

                // Add dependencies that were queued up as we processed the DEPENDENCY
                // elements:
                //
                foreach ($this->Dependencies[$subproject->GetId()] as $addid) {
                    if (array_key_exists($addid, $this->SubProjects)) {
                        $subproject->AddDependency(intval($addid));
                    } else {
                        Log::warning('impossible condition: should NEVER see this: unknown DEPENDENCY clause should prevent this case', [
                            'function' => 'ProjectHandler:endElement',
                            'projectid' => $this->GetProject()->Id,
                        ]);
                    }
                }
            }

            if (config('cdash.delete_old_subprojects')) {
                // Delete old subprojects that weren't included in this file.
                $previousSubProjectIds = $this->GetProject()->GetSubProjects()->pluck('id')->toArray();
                foreach ($previousSubProjectIds as $previousId) {
                    $found = false;
                    foreach ($this->SubProjects as $subproject) {
                        if ($subproject->GetId() == $previousId) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $subProjectToRemove = new SubProject();
                        $subProjectToRemove->SetId($previousId);
                        $subProjectToRemove->Delete();
                        Log::warning('Deleted ' . $subProjectToRemove->GetName() . ' because it was not mentioned in Project.xml', [
                            'function' => 'ProjectHandler:endElement',
                            'projectid' => $this->GetProject()->Id,
                        ]);
                    }
                }
            }
        } elseif ($name == 'SUBPROJECT') {
            // Insert the SubProject.
            $this->SubProject->SetPosition($this->SubProjectPosition);
            $this->SubProject->Save();
            $this->SubProjectPosition++;

            // Insert the label.
            $Label = new Label();
            $Label->Text = $this->SubProject->GetName();
            $Label->Insert();

            $this->SubProjects[$this->SubProject->GetId()] = $this->SubProject;

            // Handle dependencies here too.
            $this->Dependencies[$this->SubProject->GetId()] = [];
            foreach ($this->CurrentDependencies as $dependencyid) {
                $added = false;

                if ($dependencyid !== false && is_numeric($dependencyid)) {
                    if (array_key_exists($dependencyid, $this->SubProjects)) {
                        $this->Dependencies[$this->SubProject->GetId()][] = $dependencyid;
                        $added = true;
                    }
                }

                if (!$added) {
                    Log::warning('Project.xml DEPENDENCY of ' . $this->SubProject->GetName() . ' not mentioned earlier in file.', [
                        'function' => 'ProjectHandler:endElement',
                        'projectid' => $this->GetProject()->Id,
                    ]);
                }
            }
        }
    }

    /** text function */
    public function text($parser, $data): void
    {
        $element = $this->getElement();
        if ($element == 'PATH') {
            $this->SubProject->SetPath($data);
        }
    }
}

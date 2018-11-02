<?php
/**
 * =========================================================================
 *   Program:   CDash - Cross-Platform Dashboard System
 *   Module:    $Id$
 *   Language:  PHP
 *   Date:      $Date$
 *   Version:   $Revision$
 *   Copyright (c) Kitware, Inc. All rights reserved.
 *   See LICENSE or http://www.cdash.org/licensing/ for details.
 *   This software is distributed WITHOUT ANY WARRANTY; without even
 *   the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR
 *   PURPOSE. See the above copyright notices for more information.
 * =========================================================================
 */

namespace CDash\Lib\Parser\CTest;

use CDash\Lib\Parser\AbstractXmlParser;
use CDash\Model\Label;
use CDash\Model\LabelEmail;
use CDash\Model\Project;
use CDash\Model\SubProject;
use CDash\Model\User;
use CDash\Model\UserProject;

/**
 * Class ProjectParser
 * @package CDash\Lib\Parser\CTest
 */
class ProjectParser extends AbstractXmlParser
{
    private $project;
    private $subProject;
    private $subProjectPosition;
    private $dependencies; // keep an array of dependencies in order to remove them
    private $subProjects; // keep an array of subprojects in order to remove them
    private $currentDependencies; // The dependencies of the current SubProject.
    private $emails; // Email addresses associated with the current SubProject.
    private $projectNameMatches;

    /**
     * ProjectParser constructor.
     * @param $projectId
     */
    public function __construct($projectId)
    {
        parent::__construct($projectId);

        // Only actually track stuff and write it into the database if the
        // Project.xml file's name element matches this project's name in the
        // database.
        //
        $this->projectNameMatches = true;
        $this->project = $this->getInstance(Project::class);
        $this->project->Id = $projectId;
        $this->project->Fill();

        $this->subProjectPosition = 1;
    }

    /**
     * @param $parser
     * @param $name
     * @param $attributes
     * @return mixed|void
     */
    public function startElement($parser, $name, $attributes)
    {
        parent::startElement($parser, $name, $attributes);

        // Check that the project name matches
        if ($name == 'PROJECT') {
            if (get_project_id($attributes['NAME']) != $this->projectId) {
                add_log('Wrong project name: ' . $attributes['NAME'],
                    'ProjectHandler::startElement', LOG_ERR, $this->projectId);
                $this->projectNameMatches = false;
            }
        }

        if (!$this->projectNameMatches) {
            return;
        }

        if ($name == 'PROJECT') {
            $this->subProjects = array();
            $this->dependencies = array();
        } elseif ($name == 'SUBPROJECT') {
            $this->currentDependencies = array();
            $this->subProject = $this->getInstance(SubProject::class);
            $this->subProject->SetProjectId($this->projectId);
            $this->subProject->SetName($attributes['NAME']);
            if (array_key_exists('GROUP', $attributes)) {
                $this->subProject->SetGroup($attributes['GROUP']);
            }
            $this->emails = [];
        } elseif ($name == 'DEPENDENCY') {
            // A DEPENDENCY is expected to be:
            //
            //  - another subproject that already exists
            //    (from a previous element in this submission)
            //
            $dependentProject = $this->getInstance(SubProject::class);
            $dependentProject->SetName($attributes['NAME']);
            $dependentProject->SetProjectId($this->projectId);
            // The subproject's Id is automatically loaded once its name & projectid
            // are set.
            $this->currentDependencies[] = $dependentProject->GetId();
        } elseif ($name == 'EMAIL') {
            $this->emails[] = $attributes['ADDRESS'];
        }
    }

    /**
     * @param $parser
     * @param $name
     * @return mixed|void
     */
    public function endElement($parser, $name)
    {
        parent::endElement($parser, $name);
        $config = \CDash\Config::getInstance();

        if (!$this->projectNameMatches) {
            return;
        }

        if ($name == 'PROJECT') {
            foreach ($this->subProjects as $subproject) {
                if ($config->get('CDASH_DELETE_OLD_SUBPROJECTS')) {
                    // Remove dependencies that do not exist anymore,
                    // but only for those relationships where both sides
                    // are present in $this->subProjects.
                    //
                    $dependencyids = $subproject->GetDependencies();
                    $removeids = array_diff($dependencyids, $this->dependencies[$subproject->GetId()]);
                    foreach ($removeids as $removeid) {
                        if (array_key_exists($removeid, $this->subProjects)) {
                            $subproject->RemoveDependency($removeid);
                        } else {
                            $dep = pdo_get_field_value("SELECT name FROM subproject WHERE id='$removeid'", 'name', "$removeid");
                            add_log(
                                "Not removing dependency $dep($removeid) from " .
                                $subproject->GetName() .
                                'because it is not a SubProject element in this Project.xml file',
                                'ProjectHandler:endElement', LOG_WARNING, $this->projectId);
                        }
                    }
                }

                // Add dependencies that were queued up as we processed the DEPENDENCY
                // elements:
                //
                foreach ($this->dependencies[$subproject->GetId()] as $addid) {
                    if (array_key_exists($addid, $this->subProjects)) {
                        $subproject->AddDependency($addid);
                    } else {
                        add_log(
                            'impossible condition: should NEVER see this: unknown DEPENDENCY clause should prevent this case',
                            'ProjectHandler:endElement', LOG_WARNING, $this->projectId);
                    }
                }
            }

            if ($config->get('CDASH_DELETE_OLD_SUBPROJECTS')) {
                // Delete old subprojects that weren't included in this file.
                $previousSubProjectIds = $this->project->GetSubProjects();
                foreach ($previousSubProjectIds as $previousId) {
                    $found = false;
                    foreach ($this->subProjects as $subproject) {
                        if ($subproject->GetId() == $previousId) {
                            $found = true;
                            break;
                        }
                    }
                    if (!$found) {
                        $subProjectToRemove = $this->getInstance(SubProject::class);
                        $subProjectToRemove->SetId($previousId);
                        $subProjectToRemove->Delete();
                        add_log("Deleted " . $subProjectToRemove->GetName() . " because it was not mentioned in Project.xml",
                            'ProjectHandler:endElement', LOG_WARNING,
                            $this->projectId);
                    }
                }
            }
        } elseif ($name == 'SUBPROJECT') {
            // Insert the SubProject.
            $this->subProject->SetPosition($this->subProjectPosition);
            $this->subProject->Save();
            $this->subProjectPosition++;

            // Insert the label.
            $Label = $this->getInstance(Label::class);
            $Label->Text = $this->subProject->GetName();
            $Label->Insert();

            $this->subProjects[$this->subProject->GetId()] = $this->subProject;

            // Handle dependencies here too.
            $this->dependencies[$this->subProject->GetId()] = array();
            foreach ($this->currentDependencies as $dependencyid) {
                $added = false;

                if ($dependencyid !== false && is_numeric($dependencyid)) {
                    if (array_key_exists($dependencyid, $this->subProjects)) {
                        $this->dependencies[$this->subProject->GetId()][] = $dependencyid;
                        $added = true;
                    }
                }

                if (!$added) {
                    add_log('Project.xml DEPENDENCY of ' . $this->subProject->GetName() .
                        ' not mentioned earlier in file.',
                        'ProjectHandler:endElement', LOG_WARNING, $this->projectId);
                }
            }

            foreach ($this->emails as $email) {
                // Check if the user is in the database.
                $User = $this->getInstance(User::class);

                $posat = strpos($email, '@');
                if ($posat !== false) {
                    $User->FirstName = substr($email, 0, $posat);
                    $User->LastName = substr($email, $posat + 1);
                } else {
                    $User->FirstName = $email;
                    $User->LastName = $email;
                }
                $User->Email = $email;
                $User->Password = User::PasswordHash($email);
                $User->Admin = 0;
                $userid = $User->GetIdFromEmail($email);
                if (!$userid) {
                    $User->Save();
                    $userid = $User->Id;
                }

                $UserProject = $this->getInstance(UserProject::class);
                $UserProject->UserId = $userid;
                $UserProject->ProjectId = $this->projectId;
                if (!$UserProject->FillFromUserId()) {
                    // This user wasn't already subscribed to this project.
                    $UserProject->EmailType = 3; // any build
                    $UserProject->EmailCategory = 54; // everything except warnings
                    $UserProject->Save();
                }

                // Insert the labels for this user
                $LabelEmail = $this->getInstance(LabelEmail::class);
                $LabelEmail->UserId = $userid;
                $LabelEmail->ProjectId = $this->projectId;

                $Label = $this->getInstance(Label::class);
                $Label->SetText($this->subProject->GetName());
                $labelid = $Label->GetIdFromText();
                if (!empty($labelid)) {
                    $LabelEmail->LabelId = $labelid;
                    $LabelEmail->Insert();
                }
            }
        }
    }

    /**
     * @param $parser
     * @param $data
     * @return mixed|void
     */
    public function text($parser, $data)
    {
        $element = $this->getElement();
        if ($element == 'PATH') {
            $this->subProject->SetPath($data);
        }
    }
}

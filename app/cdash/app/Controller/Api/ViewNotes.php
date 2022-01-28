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

namespace CDash\Controller\Api;

use App\Services\TestingDay;

use CDash\Database;
use CDash\Model\Build;
use CDash\Model\Site;
use CDash\Model\Project;

class ViewNotes extends BuildApi
{
    private $extraMeasurements;
    private $numExtraMeasurements;

    public function __construct(Database $db, Build $build)
    {
        parent::__construct($db, $build);
        $this->project->Fill();
    }

    public function getResponse()
    {
        $response = begin_JSON_response();
        $response['title'] = "CDash : {$this->project->Name}";

        $this->setDate(TestingDay::get($this->project, $this->build->StartTime));
        get_dashboard_JSON_by_name($this->project->Name, $this->date, $response);

        // Menu
        $menu = [];
        if ($this->build->GetParentId() > 0) {
            $menu['back'] = '/index.php?project=' . urlencode($this->project->Name) . "&parentid={$this->build->GetParentId()}";
        } else {
            $menu['back'] = '/index.php?project=' . urlencode($this->project->Name) . '&date=' . $this->date;
        }

        $previous_buildid = $this->build->GetPreviousBuildId();
        $current_buildid = $this->build->GetCurrentBuildId();
        $next_buildid = $this->build->GetNextBuildId();

        if ($previous_buildid > 0) {
            $menu['previous'] = "/build/$previous_buildid/notes";
        } else {
            $menu['previous'] = false;
        }

        $menu['current'] = "/build/$current_buildid/notes";

        if ($next_buildid > 0) {
            $menu['next'] = "/build/$next_buildid/notes";
        } else {
            $menu['next'] = false;
        }

        $response['menu'] = $menu;

        // Build/site info.
        $site_name = $this->build->GetSite()->GetName();
        $response['build'] = Build::MarshalResponseArray($this->build, ['site' => $site_name]);

        // Notes for this build.
        $build2note_stmt = $this->db->prepare(
            'SELECT noteid, time FROM build2note WHERE buildid = :buildid');
        $this->db->execute($build2note_stmt, [':buildid' => $this->build->Id]);

        $note_stmt = $this->db->prepare(
            'SELECT * FROM note WHERE id = :noteid');

        $notes = [];
        while ($build2note_row = $build2note_stmt->fetch()) {
            $noteid = $build2note_row['noteid'];
            $this->db->execute($note_stmt, [':noteid' => $noteid]);
            $note_row = $note_stmt->fetch();
            $note = [];
            $note['name'] = $note_row['name'];
            $note['text'] = $note_row['text'];
            $note['time'] = $build2note_row['time'];
            $notes[] = $note;
        }
        $response['notes'] = $notes;

        $this->pageTimer->end($response);
        return $response;
    }
}

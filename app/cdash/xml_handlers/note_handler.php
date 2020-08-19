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

require_once 'xml_handlers/abstract_handler.php';

use CDash\Model\Build;
use CDash\Model\BuildConfigure;
use CDash\Model\BuildInformation;
use CDash\Model\BuildNote;
use CDash\Model\Site;
use CDash\Model\SiteInformation;

class NoteHandler extends AbstractHandler
{
    private $BuildId;
    private $Note;
    private $Configure;

    /** Constructor */
    public function __construct($projectID)
    {
        parent::__construct($projectID);
        $this->Build = new Build();
        $this->Site = new Site();
        $this->Configure = new BuildConfigure();
    }

    /** startElement function */
    public function startElement($parser, $name, $attributes)
    {
        parent::startElement($parser, $name, $attributes);
        if ($name == 'SITE') {
            $this->Site->Name = $attributes['NAME'];
            if (empty($this->Site->Name)) {
                $this->Site->Name = '(empty)';
            }
            $this->Site->Insert();

            $siteInformation = new SiteInformation();
            $buildInformation = new BuildInformation();

            // Fill in the attribute
            foreach ($attributes as $key => $value) {
                $siteInformation->SetValue($key, $value);
                $buildInformation->SetValue($key, $value);
            }

            $this->Site->SetInformation($siteInformation);

            $this->Build->SiteId = $this->Site->Id;
            $this->Build->Name = $attributes['BUILDNAME'];
            if (empty($this->Build->Name)) {
                $this->Build->Name = '(empty)';
            }
            $this->Build->SetStamp($attributes['BUILDSTAMP']);
            $this->Build->Generator = $attributes['GENERATOR'];
            $this->Build->Information = $buildInformation;
        } elseif ($name == 'NOTE') {
            $this->Note = new BuildNote();
            $this->Note->Name =
                isset($attributes['NAME']) ? $attributes['NAME'] : '';
        }
    }

    /** endElement function */
    public function endElement($parser, $name)
    {
        parent::endElement($parser, $name);
        if ($name == 'NOTE') {
            $this->Build->ProjectId = $this->projectid;
            $this->Build->GetIdFromName($this->SubProjectName);
            $this->Build->RemoveIfDone();

            // If the build doesn't exist we add it.
            if ($this->Build->Id == 0) {
                $this->Build->SetSubProject($this->SubProjectName);

                // Since we only have precision in minutes (not seconds) here,
                // set the start time at the end of the minute so it can be overridden
                // by any more precise XML file received later.
                $start_time = gmdate(FMT_DATETIME, strtotime($this->Note->Time) + 59);
                $this->Build->StartTime = $start_time;

                $this->Build->EndTime = $this->Note->Time;
                $this->Build->SubmitTime = gmdate(FMT_DATETIME);
                $this->Build->InsertErrors = false;
                add_build($this->Build);
            }

            if ($this->Build->Id > 0 && $this->Note->Time && $this->Note->Name && $this->Note->Text) {
                // Insert the note
                $this->Note->BuildId = $this->Build->Id;
                $this->Note->Insert();
            } else {
                add_log('Trying to add a note to a nonexistent build', 'note_handler.php', LOG_ERR);
            }
        }
    }

    /** text function */
    public function text($parser, $data)
    {
        $parent = $this->getParent();
        $element = $this->getElement();
        if ($parent == 'NOTE') {
            switch ($element) {
                case 'DATETIME':
                    $this->Note->Time = gmdate(FMT_DATETIME, str_to_time($data, $this->Build->GetStamp()));
                    break;
                case 'TEXT':
                    $this->Note->Text .= $data;
                    break;
            }
        }
    }
}

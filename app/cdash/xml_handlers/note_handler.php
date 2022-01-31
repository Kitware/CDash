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

use App\Services\NoteCreator;

use CDash\Model\Build;
use CDash\Model\BuildConfigure;
use CDash\Model\BuildInformation;
use CDash\Model\BuildNote;
use CDash\Model\Site;
use CDash\Model\SiteInformation;

class NoteHandler extends AbstractHandler
{
    private $AdjustStartTime;
    private $BuildId;
    private $NoteCreator;
    private $Configure;
    private $TimeStamp;

    /** Constructor */
    public function __construct($projectID)
    {
        parent::__construct($projectID);
        $this->Build = new Build();
        $this->Site = new Site();
        $this->Configure = new BuildConfigure();

        $this->AdjustStartTime = false;
        $this->Timestamp = 0;
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
            $this->NoteCreator = new NoteCreator;
            $this->NoteCreator->name =
                isset($attributes['NAME']) ? $attributes['NAME'] : '';
            $this->Timestamp = 0;
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

            $this->NoteCreator->time = gmdate(FMT_DATETIME, $this->Timestamp);

            // If the build doesn't exist we add it.
            if ($this->Build->Id == 0) {
                $this->Build->SetSubProject($this->SubProjectName);

                $build_start_timestamp = $this->Timestamp;
                if ($this->AdjustStartTime) {
                    // Since we only have precision in minutes (not seconds) here,
                    // set the start time at the end of the minute so it can be overridden
                    // by any more precise XML file received later.
                    $build_start_timestamp = $this->Timestamp + 59;
                }
                $this->Build->StartTime = gmdate(FMT_DATETIME, $build_start_timestamp);
                $this->Build->EndTime = $this->NoteCreator->time;
                $this->Build->SubmitTime = gmdate(FMT_DATETIME);
                $this->Build->InsertErrors = false;
                add_build($this->Build);
            }

            if ($this->Build->Id > 0 && $this->NoteCreator->time && $this->NoteCreator->name && $this->NoteCreator->text) {
                // Insert the note
                $this->NoteCreator->buildid = $this->Build->Id;
                $this->NoteCreator->create();
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
                    // Only use the <DateTime> element when <Time> is unsuitable.
                    if ($this->Timestamp === 0) {
                        $this->Timestamp =
                            $this->getTimestampFromDateTimeElement($data, $this->Build->GetStamp());
                        $this->AdjustStartTime = true;
                    }
                    break;
                case 'TIME':
                    // Prefer the <Time> element if it wasn't specified in
                    // scientific notation (CTest v3.11.0 or newer).
                    if (strpos($data, 'e+') === false) {
                        $this->Timestamp = $data;
                        $this->AdjustStartTime = false;
                    }
                    break;
                case 'TEXT':
                    $this->NoteCreator->text .= $data;
                    break;
            }
        }
    }

    private function getTimestampFromDateTimeElement($str, $stamp)
    {
        $str = str_replace('Eastern Standard Time', 'EST', $str);
        $str = str_replace('Eastern Daylight Time', 'EDT', $str);

        // For some reasons the Australian time is not recognized by php
        // Actually an open bug in PHP 5.
        $offset = 0; // no offset by default
        if (strpos($str, 'AEDT') !== false) {
            $str = str_replace('AEDT', 'UTC', $str);
            $offset = 3600 * 11;
        } // We had more custom dates
        elseif (strpos($str, 'Paris, Madrid') !== false) {
            $str = str_replace('Paris, Madrid', 'UTC', $str);
            $offset = 3600 * 1;
        } elseif (strpos($str, 'W. Europe Standard Time') !== false) {
            $str = str_replace('W. Europe Standard Time', 'UTC', $str);
            $offset = 3600 * 1;
        }

        // The year is always at the end of the string if it exists (from CTest)
        $stampyear = substr($stamp, 0, 4);
        $year = substr($str, strlen($str) - 4, 2);

        if ($year != '19' && $year != '20') {
            // No year is defined we add it
            // find the hours
            $pos = strpos($str, ':');
            if ($pos !== false) {
                $tempstr = $str;
                $str = substr($tempstr, 0, $pos - 2);
                $str .= $stampyear . ' ' . substr($tempstr, $pos - 2);
            }
        }

        $strtotimefailed = 0;

        if (strtotime($str) === false) {
            $strtotimefailed = 1;
        }

        // If it's still failing we assume GMT and put the year at the end
        if ($strtotimefailed) {
            // find the hours
            $pos = strpos($str, ':');
            if ($pos !== false) {
                $tempstr = $str;
                $str = substr($tempstr, 0, $pos - 2);
                $str .= substr($tempstr, $pos - 2, 5);
            }
        }
        return strtotime($str) - $offset;
    }
}

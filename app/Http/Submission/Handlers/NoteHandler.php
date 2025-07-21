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

use App\Http\Submission\Traits\UpdatesSiteInformation;
use App\Models\Site;
use App\Models\SiteInformation;
use App\Utils\NoteCreator;
use App\Utils\SubmissionUtils;
use CDash\Model\Project;
use Illuminate\Support\Facades\Log;

class NoteHandler extends AbstractXmlHandler
{
    use UpdatesSiteInformation;

    private $AdjustStartTime;
    private $NoteCreator;
    protected static ?string $schema_file = '/app/Validators/Schemas/Notes.xsd';

    /** Constructor */
    public function __construct(Project $project)
    {
        parent::__construct($project);
        $this->Site = new Site();

        $this->AdjustStartTime = false;
        $this->Timestamp = 0;
    }

    /** startElement function */
    public function startElement($parser, $name, $attributes): void
    {
        parent::startElement($parser, $name, $attributes);
        if ($this->currentPathMatches('site')) {
            $site_name = !empty($attributes['NAME']) ? $attributes['NAME'] : '(empty)';
            $this->Site = Site::firstOrCreate(['name' => $site_name], ['name' => $site_name]);

            $siteInformation = new SiteInformation();

            // Fill in the attribute
            foreach ($attributes as $key => $value) {
                $siteInformation->SetValue($key, $value);
                switch ($key) {
                    case 'OSNAME':
                        $this->Build->OSName = $value;
                        break;
                    case 'OSRELEASE':
                        $this->Build->OSRelease = $value;
                        break;
                    case 'OSVERSION':
                        $this->Build->OSVersion = $value;
                        break;
                    case 'OSPLATFORM':
                        $this->Build->OSPlatform = $value;
                        break;
                    case 'COMPILERNAME':
                        $this->Build->CompilerName = $value;
                        break;
                    case 'COMPILERVERSION':
                        $this->Build->CompilerVersion = $value;
                        break;
                }
            }

            $this->updateSiteInfoIfChanged($this->Site, $siteInformation);

            $this->Build->SiteId = $this->Site->id;
            $this->Build->Name = $attributes['BUILDNAME'];
            if (empty($this->Build->Name)) {
                $this->Build->Name = '(empty)';
            }
            $this->Build->SetStamp($attributes['BUILDSTAMP']);
            $this->Build->Generator = $attributes['GENERATOR'];
        } elseif ($name == 'NOTE') {
            $this->NoteCreator = new NoteCreator();
            $this->NoteCreator->name =
                $attributes['NAME'] ?? '';
            $this->Timestamp = 0;
        }
    }

    /** endElement function */
    public function endElement($parser, $name): void
    {
        parent::endElement($parser, $name);
        if ($name == 'NOTE') {
            $this->Build->ProjectId = $this->GetProject()->Id;
            $this->Build->GetIdFromName($this->SubProjectName);
            $this->Build->RemoveIfDone();

            $this->NoteCreator->time = gmdate(FMT_DATETIME, $this->Timestamp);

            if ($this->Timestamp === 0) {
                Log::error("Cannot create build '{$this->Build->Name}' for note '{$this->NoteCreator->name}' because time was not set");
            } elseif ($this->Build->Id == 0) {
                // If the build doesn't exist we add it.
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
                SubmissionUtils::add_build($this->Build);
            }

            if (!$this->Build->Id) {
                Log::error("Trying to add note '{$this->NoteCreator->name}' to a nonexistent build");
            } elseif (!$this->NoteCreator->name) {
                Log::error("Note missing name for build #{$this->Build->Id}");
            } elseif (!$this->NoteCreator->text) {
                Log::debug("No note text for '{$this->NoteCreator->name}' on build #{$this->Build->Id}");
            } elseif ($this->Timestamp === 0) {
                Log::error("No note time for '{$this->NoteCreator->name}' on build #{$this->Build->Id}");
            } else {
                // Insert the note
                $this->NoteCreator->buildid = $this->Build->Id;
                $this->NoteCreator->create();
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
                    if (!str_contains($data, 'e+')) {
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
        if (str_contains($str, 'AEDT')) {
            $str = str_replace('AEDT', 'UTC', $str);
            $offset = 3600 * 11;
        } // We had more custom dates
        elseif (str_contains($str, 'Paris, Madrid')) {
            $str = str_replace('Paris, Madrid', 'UTC', $str);
            $offset = 3600 * 1;
        } elseif (str_contains($str, 'W. Europe Standard Time')) {
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

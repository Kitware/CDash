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
use CDash\Model\Build;
use CDash\Model\BuildConfigure;
use CDash\Model\BuildInformation;
use CDash\Model\BuildNote;
use CDash\Model\Site;
use CDash\Model\SiteInformation;

/**
 * Class NoteParser
 * @package CDash\Lib\Parser\CTest
 */
class NoteParser extends AbstractXmlParser
{
    protected $note;
    protected $configure;

    /** Constructor */
    public function __construct($projectId)
    {
        parent::__construct($projectId);
        $this->build = $this->getInstance(Build::class);
        $this->site = $this->getInstance(Site::class);
        $this->configure = $this->getInstance(BuildConfigure::class);
    }

    /** startElement function */
    public function startElement($parser, $name, $attributes)
    {
        parent::startElement($parser, $name, $attributes);
        if ($name == 'SITE') {
            $this->site->Name = $attributes['NAME'];
            if (empty($this->site->Name)) {
                $this->site->Name = '(empty)';
            }
            $this->site->Insert();

            $siteInformation = $this->getInstance(SiteInformation::class);
            $buildInformation = $this->getInstance(BuildInformation::class);

            // Fill in the attribute
            foreach ($attributes as $key => $value) {
                $siteInformation->SetValue($key, $value);
                $buildInformation->SetValue($key, $value);
            }

            $this->site->SetInformation($siteInformation);

            $this->build->SiteId = $this->site->Id;
            $this->build->Name = $attributes['BUILDNAME'];
            if (empty($this->build->Name)) {
                $this->build->Name = '(empty)';
            }
            $this->build->SetStamp($attributes['BUILDSTAMP']);
            $this->build->Generator = $attributes['GENERATOR'];
            $this->build->Information = $buildInformation;
        } elseif ($name == 'NOTE') {
            $this->note = $this->getInstance(BuildNote::class);
            $this->note->Name =
                isset($attributes['NAME']) ? $attributes['NAME'] : '';
        }
    }

    /** endElement function */
    public function endElement($parser, $name)
    {
        parent::endElement($parser, $name);
        if ($name == 'NOTE') {
            $this->build->ProjectId = $this->projectId;
            $this->build->GetIdFromName($this->subProjectName);
            $this->build->RemoveIfDone();

            // If the build doesn't exist we add it.
            if ($this->build->Id == 0) {
                $this->build->SetSubProject($this->subProjectName);

                // Since we only have precision in minutes (not seconds) here,
                // set the start time at the end of the minute so it can be overridden
                // by any more precise XML file received later.
                $start_time = gmdate(FMT_DATETIME, strtotime($this->note->Time) + 59);
                $this->build->StartTime = $start_time;

                $this->build->EndTime = $this->note->Time;
                $this->build->SubmitTime = gmdate(FMT_DATETIME);
                $this->build->InsertErrors = false;
                add_build($this->build);
            }

            if ($this->build->Id > 0) {
                // Insert the note
                $this->note->BuildId = $this->build->Id;
                $this->note->Insert();
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
                    $this->note->Time = gmdate(FMT_DATETIME, str_to_time($data, $this->build->GetStamp()));
                    break;
                case 'TEXT':
                    $this->note->Text .= $data;
                    break;
            }
        }
    }
}

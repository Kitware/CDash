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

use App\Models\PendingSubmissions;
use CDash\Model\Build;
use CDash\Model\Repository;

class DoneHandler extends AbstractXmlHandler
{
    private bool $FinalAttempt = false;
    private bool $Requeue = false;
    public string $backupFileName;
    protected static ?string $schema_file = '/app/Validators/Schemas/Done.xsd';

    public function __construct(Build $build)
    {
        parent::__construct($build);
    }

    public function startElement($parser, $name, $attributes): void
    {
        parent::startElement($parser, $name, $attributes);
        if ($name === 'DONE' && array_key_exists('RETRIES', $attributes)
                && $attributes['RETRIES'] > 4) {
            // Too many retries, stop trying to parse this file.
            $this->FinalAttempt = true;
        }
    }

    public function endElement($parser, $name): void
    {
        parent::endElement($parser, $name);
        if ($name === 'DONE') {
            $pendingSubmissionsModel = PendingSubmissions::firstWhere('buildid', (int) $this->Build->Id);

            // Check pending submissions and requeue this file if necessary.
            if ($pendingSubmissionsModel !== null && $pendingSubmissionsModel->numfiles > 1) {
                // There are still pending submissions.
                if (!$this->FinalAttempt) {
                    // Requeue this Done.xml file so that we can attempt to parse
                    // it again at a later date.
                    $this->Requeue = true;
                }
                return;
            }

            $this->Build->UpdateBuild($this->Build->Id, -1, -1);
            $this->Build->MarkAsDone(true);

            // Should we re-run any checks that were previously marked
            // as pending?
            if ($pendingSubmissionsModel !== null && $pendingSubmissionsModel->recheck) {
                $revision = \App\Models\Build::findOrFail((int) $this->Build->Id)->updateStep->revision ?? '';
                Repository::createOrUpdateCheck($revision);
            }

            $pendingSubmissionsModel?->delete();

            // Set the status of this build on our repository.
            Repository::setStatus($this->Build, true);
        }
    }

    public function text($parser, $data): void
    {
        $parent = $this->getParent();
        $element = $this->getElement();
        if ($parent === 'DONE') {
            switch ($element) {
                case 'BUILDID':
                    $this->Build->Id = $data;
                    $this->Build->FillFromId($this->Build->Id);
                    break;
                case 'TIME':
                    $this->Build->EndTime = gmdate(FMT_DATETIME, $data);
                    break;
            }
        }
    }

    public function getSiteName(): string
    {
        return $this->Build->GetSite()->name;
    }

    public function shouldRequeue()
    {
        return $this->Requeue;
    }
}

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
use CDash\Model\PendingSubmissions;
use CDash\Model\Repository;

class DoneHandler extends AbstractHandler
{
    private $FinalAttempt;
    private $PendingSubmissions;
    private $Requeue;

    public function __construct($projectID, $scheduleID)
    {
        parent::__construct($projectID, $scheduleID);
        $this->Build = new Build();
        $this->FinalAttempt = false;
        $this->PendingSubmissions = new PendingSubmissions();
        $this->Requeue = false;
    }

    public function startElement($parser, $name, $attributes)
    {
        parent::startElement($parser, $name, $attributes);
        if ($name == 'DONE' && array_key_exists('RETRIES', $attributes) &&
                $attributes['RETRIES'] > 4) {
            // Too many retries, stop trying to parse this file.
            $this->FinalAttempt = true;
        }
    }

    public function endElement($parser, $name)
    {
        parent::endElement($parser, $name);
        if ($name == 'DONE') {
            // Check pending submissions and requeue this file if necessary.
            $this->PendingSubmissions->Build = $this->Build;
            if ($this->PendingSubmissions->GetNumFiles() > 1) {
                // There are still pending submissions.
                if (!$this->FinalAttempt) {
                    // Requeue this Done.xml file so that we can attempt to parse
                    // it again at a later date.
                    $this->Requeue = true;
                }
                return;
            }

            $this->Build->UpdateBuild($this->Build->Id, -1, -1);
            $this->Build->MarkAsDone(1);
            if ($this->PendingSubmissions->Exists()) {
                $this->PendingSubmissions->Delete();
            }

            // Set the status of this build on our repository.
            Repository::setStatus($this->Build, true);
        }
    }

    public function text($parser, $data)
    {
        $parent = $this->getParent();
        $element = $this->getElement();
        if ($parent == 'DONE') {
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

    public function getSiteName()
    {
        return $this->Build->GetSite()->GetName();
    }

    public function shouldRequeue()
    {
        return $this->Requeue;
    }
}

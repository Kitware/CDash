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

namespace CDash\Lib\Parsing\Xml;

use CDash\Model\Build;
use CDash\Model\PendingSubmissions;

/**
 * Class DoneParser
 * @package CDash\Lib\Parsing\Xml
 */
class DoneParser extends AbstractXmlParser
{
    private $finalAttempt;
    private $pendingSubmissions;
    private $requeue;

    /**
     * DoneParser constructor.
     * @param $projectId
     */
    public function __construct($projectId)
    {
        parent::__construct($projectId);
        $this->build = $this->getInstance(Build::class);
        $this->finalAttempt = false;
        $this->pendingSubmissions = $this->getInstance(PendingSubmissions::class);
        $this->requeue = false;
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
        if ($name == 'DONE' && array_key_exists('RETRIES', $attributes) &&
            $attributes['RETRIES'] > 4) {
            // Too many retries, stop trying to parse this file.
            $this->finalAttempt = true;
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
        if ($name == 'DONE') {
            // Check pending submissions and requeue this file if necessary.
            $this->pendingSubmissions->Build = $this->build;
            if ($this->pendingSubmissions->GetNumFiles() > 1) {
                // There are still pending submissions.
                if (!$this->finalAttempt) {
                    // Requeue this Done.xml file so that we can attempt to parse
                    // it again at a later date.
                    $this->requeue = true;
                }
                return;
            }

            $this->build->UpdateBuild($this->build->Id, -1, -1);
            $this->build->MarkAsDone(1);
            if ($this->pendingSubmissions->Exists()) {
                $this->pendingSubmissions->Delete();
            }
            // TODO: notifications.
        }
    }

    /**
     * @param $parser
     * @param $data
     * @return mixed|void
     */
    public function text($parser, $data)
    {
        $parent = $this->getParent();
        $element = $this->getElement();
        if ($parent == 'DONE') {
            switch ($element) {
                case 'BUILDID':
                    $this->build->Id = $data;
                    $this->build->FillFromId($this->build->Id);
                    break;
                case 'TIME':
                    $this->build->EndTime = gmdate(FMT_DATETIME, $data);
                    break;
            }
        }
    }

    /**
     * @return string
     */
    public function getSiteName()
    {
        return $this->build->GetSite()->GetName();
    }

    /**
     * @return bool
     */
    public function shouldRequeue()
    {
        return $this->requeue;
    }
}

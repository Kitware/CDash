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

namespace CDash\Messaging\Notification\Email;

use CDash\Collection\BuildEmailCollection;
use Illuminate\View\View;

class EmailMessage
{
    protected string $sender;
    protected string $recipient;
    protected string $subject;
    private string $body;
    private BuildEmailCollection $buildEmailCollection;

    public function __construct()
    {
        $this->buildEmailCollection = new BuildEmailCollection();
    }

    public function setBuildEmailCollection(BuildEmailCollection $collection): self
    {
        $this->buildEmailCollection = $collection;
        return $this;
    }

    public function getBuildEmailCollection(): BuildEmailCollection
    {
        return $this->buildEmailCollection;
    }

    public function setRecipient(string $recipient): self
    {
        $this->recipient = $recipient;
        return $this;
    }

    public function getRecipient(): string
    {
        return $this->recipient;
    }

    public function setBody(View $body): self
    {
        $this->body = trim($body->render());
        return $this;
    }

    public function setSubject(View $subject): self
    {
        $this->subject = trim($subject->render());
        return $this;
    }

    public function getSubject(): string
    {
        return trim($this->subject);
    }

    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * TODO: this should probably return all headers + body see (RFC 2822)
     */
    public function __toString(): string
    {
        return $this->body;
    }
}

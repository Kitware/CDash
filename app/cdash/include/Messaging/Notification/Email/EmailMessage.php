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
use CDash\Messaging\Notification\Email\Decorator\DecoratorInterface;
use CDash\Messaging\Notification\NotificationInterface;

class EmailMessage implements NotificationInterface
{
    /** @var  string $sender */
    protected $sender;

    /** @var  string $recipient */
    protected $recipient;

    /** @var  string $subject */
    protected $subject;

    /** @var  string $body */
    private $body;

    /** @var BuildEmailCollection $buildEmailCollection */
    private $buildEmailCollection;

    /**
     * @param BuildEmailCollection $collection
     * @return $this
     */
    public function setBuildEmailCollection(BuildEmailCollection $collection)
    {
        $this->buildEmailCollection = $collection;
        return $this;
    }

    /**
     * @return BuildEmailCollection
     */
    public function getBuildEmailCollection()
    {
        if (!$this->buildEmailCollection) {
            $this->buildEmailCollection = new BuildEmailCollection();
        }

        return $this->buildEmailCollection;
    }

    /**
     * @param $sender
     * @return EmailMessage
     */
    public function setSender($sender)
    {
        $this->sender = $sender;
        return $this;
    }

    /**
     * @return string
     */
    public function getSender()
    {
        return $this->sender;
    }

    /**
     * @param $recipient
     * @return EmailMessage
     */
    public function setRecipient($recipient)
    {
        $this->recipient = $recipient;
        return $this;
    }

    /**
     * @return string
     */
    public function getRecipient()
    {
        return $this->recipient;
    }

    /**
     * @param DecoratorInterface $body
     * @return EmailMessage
     */
    public function setBody($body)
    {
        $this->body = $body;
        return $this;
    }

    /**
     * @param $subject
     * @return EmailMessage
     */
    public function setSubject($subject)
    {
        $this->subject = trim($subject);
        return $this;
    }

    /**
     * @return string
     */
    public function getSubject()
    {
        return trim($this->subject);
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return trim($this->body);
    }

    /**
     * @return string
     * TODO: this should probably return all headers + body see (RFC 2822)
     */
    public function __toString()
    {
        $body = '';
        if (is_string($this->body)) {
            $body = $this->body;
        } elseif (method_exists($this->body, '__toString')) {
            $body = $this->body->__toString();
        }
        return $body;
    }
}

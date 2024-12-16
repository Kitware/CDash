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

use CDash\Singleton;

class Mail extends Singleton
{
    protected $mailer;
    protected $recipient;
    protected $emails;

    public function __construct()
    {
        $this->emails = [];
    }

    public function setRecipient($recipient)
    {
        $this->recipient = $recipient;
    }

    public function send(EmailMessage $message): void
    {
        cdashmail($this->recipient, $message->getSubject(), $message->getBody());
    }

    public static function to($address): Mail
    {
        $mail = self::getInstance();
        $mail->setRecipient($address);
        return $mail;
    }
}

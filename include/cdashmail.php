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


function cdashmail($to, $subject, $body, $headers = false)
{
    if (empty($to)) {
        add_log('Cannot send email. Recipient is not set.');
        return false;
    }

    global $CDASH_EMAIL_FROM, $CDASH_EMAIL_REPLY;

    $message = Swift_Message::newInstance()
        ->setTo($to)
        ->setSubject($subject)
        ->setBody($body)
        ->setFrom(array($CDASH_EMAIL_FROM => 'CDash'))
        ->setReplyTo($CDASH_EMAIL_REPLY)
        ->setContentType('text/plain')
        ->setCharset('UTF-8');

    global $CDASH_EMAIL_SMTP_HOST, $CDASH_EMAIL_SMTP_PORT,
           $CDASH_EMAIL_SMTP_ENCRYPTION, $CDASH_EMAIL_SMTP_LOGIN,
           $CDASH_EMAIL_SMTP_PASS;

    if (is_null($CDASH_EMAIL_SMTP_HOST)) {
        // Use the PHP mail() function.
        $transport = Swift_MailTransport::newInstance();
    } else {
        // Use an SMTP server to send mail.
        $transport = Swift_SmtpTransport::newInstance(
            $CDASH_EMAIL_SMTP_HOST, $CDASH_EMAIL_SMTP_PORT,
            $CDASH_EMAIL_SMTP_ENCRYPTION);

        if (!is_null($CDASH_EMAIL_SMTP_LOGIN)
            && !is_null($CDASH_EMAIL_SMTP_PASS)
        ) {
            $transport->setUsername($CDASH_EMAIL_SMTP_LOGIN)
                ->setPassword($CDASH_EMAIL_SMTP_PASS);
        }
    }

    $mailer = Swift_Mailer::newInstance($transport);
    return $mailer->send($message) > 0;
}

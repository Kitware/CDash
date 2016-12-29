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

function _cdashsendgrid($to, $subject, $body)
{
    global $CDASH_EMAIL_FROM, $CDASH_EMAIL_REPLY, $CDASH_SENDGRID_API_KEY;
    $sg = new \SendGrid($CDASH_SENDGRID_API_KEY);

    $mail = new SendGrid\Mail();
    $mail->setFrom(new SendGrid\Email(null, $CDASH_EMAIL_FROM));
    $mail->setSubject($subject);
    $mail->setReplyTo(new SendGrid\Email(null, $CDASH_EMAIL_REPLY));
    $mail->addContent(new SendGrid\Content('text/plain', $body));

    foreach (explode(', ', $to) as $recipient) {
        $personalization = new SendGrid\Personalization();
        $personalization->addTo(new SendGrid\Email(null, $recipient));
        $mail->addPersonalization($personalization);
    }

    $response = $sg->client->mail()->send()->post($mail);

    if ($response->statusCode() === 202) {
        return true;
    } else {
        add_log('Failed to send email via sendgrid status code: ' . $response->statusCode(), '_cdashsendgrid', LOG_ERR);
        return false;
    }
}

function cdashmail($to, $subject, $body, $headers = false)
{
    if (empty($to)) {
        add_log('Cannot send email. Recipient is not set.', 'cdashmail', LOG_ERR);
        return false;
    }

    global $CDASH_USE_SENDGRID;
    if ($CDASH_USE_SENDGRID) {
        return _cdashsendgrid($to, $subject, $body);
    }

    $to = explode(', ', $to);

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

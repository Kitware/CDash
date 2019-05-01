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

use CDash\Config;

function _cdashsendgrid($to, $subject, $body)
{
    $config = Config::getInstance();

    $sg = new \SendGrid($config->get('CDASH_SENDGRID_API_KEY'));

    $mail = new SendGrid\Mail();
    $mail->setFrom(new SendGrid\Email(null, $config->get('CDASH_EMAIL_FROM')));
    $mail->setSubject($subject);
    $mail->setReplyTo(new SendGrid\Email(null, $config->get('CDASH_EMAIL_REPLY')));
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
    $config = Config::getInstance();
    if (empty($to)) {
        add_log('Cannot send email. Recipient is not set.', 'cdashmail', LOG_ERR);
        return false;
    }

    if ($config->get('CDASH_USE_SENDGRID')) {
        return _cdashsendgrid($to, $subject, $body);
    }

    $to = explode(', ', $to);
    try {
        $message = Swift_Message::newInstance()
            ->setTo($to)
            ->setSubject($subject)
            ->setBody($body)
            ->setFrom([$config->get('CDASH_EMAIL_FROM')=> 'CDash'])
            ->setReplyTo($config->get('CDASH_EMAIL_REPLY'))
            ->setContentType('text/plain')
            ->setCharset('UTF-8');
    } catch (\Swift_RfcComplianceException $e) {
        add_log("Swift RFC compliance exception. to=" . print_r($to, true). ", from=[". $config->get('CDASH_EMAIL_FROM') . " => 'CDash'], 'reply-to=" . $config->get('CDASH_EMAIL_REPLY'), 'sendmail', LOG_INFO);
        return false;
    }

    if (is_null($config->get('CDASH_EMAIL_SMTP_HOST'))) {
        // Use the PHP mail() function.
        $transport = Swift_MailTransport::newInstance();
    } else {
        // Use an SMTP server to send mail.
        $transport = Swift_SmtpTransport::newInstance(
            $config->get('CDASH_EMAIL_SMTP_HOST'), $config->get('CDASH_EMAIL_SMTP_PORT'),
            $config->get('CDASH_EMAIL_SMTP_ENCRYPTION'));

        if (!is_null($config->get('CDASH_EMAIL_SMTP_LOGIN'))
            && !is_null($config->get('CDASH_EMAIL_SMTP_PASS'))
        ) {
            $transport->setUsername($config->get('CDASH_EMAIL_SMTP_LOGIN'))
                ->setPassword($config->get('CDASH_EMAIL_SMTP_PASS'));
        }
    }

    $mailer = Swift_Mailer::newInstance($transport);
    return $mailer->send($message) > 0;
}

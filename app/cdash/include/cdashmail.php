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
use Illuminate\Support\Facades\Mail;

function _cdashsendgrid($to, $subject, $body)
{
    $config = Config::getInstance();

    $sg = new \SendGrid($config->get('CDASH_SENDGRID_API_KEY'));

    $mail = new SendGrid\Mail();
    $mail->setFrom(new SendGrid\Email(null, config('mail.from.address')));
    $mail->setSubject($subject);
    $mail->setReplyTo(new SendGrid\Email(null, config('mail.reply_to.address')));
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

    if ($config->get('CDASH_TESTING_MODE')) {
        add_log($to, 'TESTING: EMAIL', LOG_DEBUG);
        add_log($subject, 'TESTING: EMAILTITLE', LOG_DEBUG);
        add_log($body, 'TESTING: EMAILBODY', LOG_DEBUG);
        return true;
    }

    Mail::raw($body, function ($message) use ($config, $to, $subject, $headers) {
        $to = is_array($to) ? $to : [$to];
        try {
            /** @var Illuminate\Mail\Message $message */
            $message->subject($subject)
                ->to($to)
                ->from(config('mail.from.address'))
                ->replyTo(config('mail.reply_to.address'));
        } catch (\Exception $e) {
            \Log::error($e->getMessage());
            return false;
        }
    });

    return true;
}

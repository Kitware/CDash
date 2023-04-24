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

use Illuminate\Support\Facades\Log;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mime\Email;

use CDash\Singleton;

class Mail extends Singleton
{
    protected $mailer;
    protected $recipient;
    protected $defaultSender;
    protected $emails;

    public function __construct()
    {
        $this->emails = [];
    }

    public function setRecipient($recipient)
    {
        $this->recipient = $recipient;
    }

    public function getDefaultSender(): string
    {
        if (!$this->defaultSender) {
            $this->defaultSender = config('mail.from.address');
        }
        return $this->defaultSender;
    }

    public function getMailer(): Mailer
    {
        if (!$this->mailer) {
            if (config('mail.driver') === 'smtp') {
                $smtp_host = config('mail.host');
                $smtp_port = config('mail.port');
                $smtp_user = config('mail.username');
                $smtp_pswd = config('mail.password');
                if ($smtp_user && $smtp_pswd) {
                    $smtp_dsn = "smtp://{$smtp_user}:{$smtp_pswd}@{$smtp_host}:{$smtp_port}";
                } else {
                    $smtp_dsn = "smtp://{$smtp_host}:{$smtp_port}";
                }
                $transport = Transport::fromDsn($smtp_dsn);
            } else {
                $transport = new SendmailTransport();
            }

            $this->mailer = new Mailer($transport);
        }
        return $this->mailer;
    }

    public function send(EmailMessage $message): void
    {
        if (config('app.debug')) {
            return;
        }
        $sender = $message->getSender() ?: $this->getDefaultSender();
        $mailer = $this->getMailer();

        try {
            $email = (new Email())
                ->from($sender)
                ->to($this->recipient)
                ->subject($message->getSubject())
                ->text($message->getBody());
        } catch (\Exception $e) {
            Log::info($e->getMessage());
        }

        try {
            $mailer->send($email);
        } catch (\Exception $e) {
            Log::info($e->getMessage());
        }
    }

    public static function to($address): Mail
    {
        $mail = self::getInstance();
        $mail->setRecipient($address);
        return $mail;
    }
}

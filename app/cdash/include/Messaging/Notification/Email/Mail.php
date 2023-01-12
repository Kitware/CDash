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

use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mailer\Transport\SendmailTransport;
use Symfony\Component\Mime\Email;

use CDash\Log;
use CDash\Messaging\Notification\NotificationInterface;
use CDash\Singleton;

class Mail extends Singleton
{
    protected $mailer;
    protected $recipient;
    protected $defaultSender;
    protected $defaultReplyTo;
    protected $emails;

    /**
     * Mail constructor.
     */
    public function __construct()
    {
        $this->emails = [];
    }

    /**
     * @param $recipient
     */
    public function setRecipient($recipient)
    {
        $this->recipient = $recipient;
    }

    /**
     * @return string
     */
    public function getDefaultSender()
    {
        if (!$this->defaultSender) {
            $this->defaultSender = config('mail.from.address');
        }
        return $this->defaultSender;
    }

    /**
     * @return string
     */
    public function getDefaultReplyTo()
    {
        if (!$this->defaultReplyTo) {
            $address = config('mail.reply_to.address');
            $name = config('mail.reply_to.name');
            $this->defaultReplyTo = "{$name} <{$address}>";
        }
        return $this->defaultReplyTo;
    }

    /**
     * @return null|Mailer
     */
    public function getMailer()
    {
        if (!$this->mailer) {
            if (config('mail.driver') == 'stmp') {
                $smtp_host = config('mail.host');
                $smtp_port = config('mail.port');
                $smtp_user = config('mail.username');
                $smtp_pswd = config('mail.password');
                if ($smtp_user && $smtp_pswd) {
                    $smtp_dsn = "smtp://{$smtp_user}:{$smtp_pswd}@{$smtp_host}:{$smtp_port}";
                } else {
                    $smtp_dsn = "smtp://{$smtp_host}:{$smtp_port}";
                }
                $transport = Transport::fromDsn($dsn);
            } else {
                $transport = new SendmailTransport();
            }

            $this->mailer = new Mailer($transport);
        }
        return $this->mailer;
    }

    public function send(EmailMessage $message)
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
            $log = Log::getInstance();
            $log->add_log($e->getMessage(), __METHOD__);
        }

        try {
            $mailer->send($email);
        } catch (\Exception $e) {
            $log = Log::getInstance();
            $log->add_log($e->getMessage(), __METHOD__);
        }
    }

    public static function to($address)
    {
        $mail = self::getInstance();
        $mail->setRecipient($address);
        return $mail;
    }

    public function addEmail(Email $email)
    {
        $this->emails[] = $email;
    }

    public function getEmails()
    {
        return $this->emails;
    }
}

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

use CDash\Config;
use CDash\Log;
use CDash\Messaging\Notification\NotificationInterface;
use CDash\Singleton;

class Mail extends Singleton
{
    protected $swift;
    protected $recipient;
    protected $defaultSender;
    protected $defaultReplyTo;
    protected $emails;

    /**
     * TODO: probably better implemented with DI
     * (e.g.: __construct(Swift_)
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
            $config = Config::getInstance();
            $this->defaultSender = $config->get('CDASH_EMAIL_FROM');
        }
        return $this->defaultSender;
    }

    /**
     * @return string
     */
    public function getDefaultReplyTo()
    {
        if (!$this->defaultReplyTo) {
            $config = Config::getInstance();
            $address = $config->get('CDASH_EMAIL_REPLY');
            $name = 'CDash';
            $this->defaultReplyTo = "{$name} <{$address}>";
        }
        return $this->defaultReplyTo;
    }

    /**
     * @return null|\Swift_Mailer
     */
    public function getSwiftMailer()
    {
        if (!$this->swift) {
            $config = Config::getInstance();
            $smtp_host = $config->get('CDASH_EMAIL_SMTP_HOST');
            if (!$smtp_host) {
                // TODO: this should never happen, discuss.
                /* @see https://github.com/swiftmailer/swiftmailer/issues/866#issuecomment-289291228
                 * If we want to enable the ability to send via php's native mail() function
                 * then we should create our own Transport implementing Swift_Transport
                 * that guarantees proper sanitization of vulnerable input, namely email addresses
                 */
                // $transport = \Swift_MailTransport::newInstance();
                $transport = new \Swift_SendmailTransport();
            } else {
                $smtp_port = $config->get('CDASH_EMAIL_SMTP_PORT');
                $smtp_user = $config->get('CDASH_EMAIL_SMTP_LOGIN');
                $smtp_pswd = $config->get('CDASH_EMAIL_STMP_PASS');

                $transport = new \Swift_SmtpTransport($smtp_host, $smtp_port);
                if ($smtp_host && $smtp_pswd) {
                    $transport
                        ->setUsername($smtp_user)
                        ->setPassword($smtp_pswd);
                }
            }

            $this->swift = new \Swift_Mailer($transport);

            if ($config->get('CDASH_TESTING_MODE')) {
                $listener = new EmailSendListener($this);
                $this->swift->registerPlugin($listener);
            }
        }
        return $this->swift;
    }

    public function send(EmailMessage $message)
    {
        $sender = $message->getSender() ?: $this->getDefaultSender();
        $swift_mailer = $this->getSwiftMailer();
        $swift_message = new \Swift_Message();
        $swift_message->setTo($this->recipient)
            ->setFrom($sender)
            ->setSubject($message->getSubject())
            ->setBody($message->getBody());
        $failed_recipients = [];

        $status = $swift_mailer->send($swift_message, $failed_recipients);
        if (!empty($failed_recipients)) {
            $log = Log::getInstance();
            $message = "Failed to send message titled {$message->getSubject()} to {$failed_recipients[0]}";
            $log->add_log($message, __METHOD__);
        }
        return $status;
    }

    public static function to($address)
    {
        $mail = self::getInstance();
        $mail->setRecipient($address);
        return $mail;
    }

    public function addEmail(\Swift_Mime_Message $message)
    {
        $this->emails[] = $message;
    }

    public function getEmails()
    {
        return $this->emails;
    }
}

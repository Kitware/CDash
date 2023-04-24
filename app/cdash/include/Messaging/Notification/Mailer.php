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

namespace CDash\Messaging\Notification;

use CDash\Messaging\Notification\Email\EmailMessage;
use CDash\Messaging\Notification\Email\Mail;
use CDash\Model\BuildEmail;
use CDash\Singleton;
use Exception;

/**
 * Class Mailer
 * @package CDash\Messaging\Notification
 */
class Mailer extends Singleton
{
    /**
     * @throws Exception
     */
    public static function send(NotificationCollection $notifications)
    {
        $mailer = self::getInstance();
        foreach ($notifications as $notification) {
            $mailer->sendNotification($notification);
        }
    }

    /**
     * @throws Exception
     */
    public function sendNotification(NotificationInterface $notification)
    {
        $type = get_class($notification);
        switch ($type) {
            case EmailMessage::class:
                Mail::to($notification->getRecipient())->send($notification);
                break;
            default:
                $message = "Unrecognized message type [{$type}]";
                throw new \Exception($message);
        }

        BuildEmail::Log($notification, true);
        BuildEmail::SaveNotification($notification);
    }
}

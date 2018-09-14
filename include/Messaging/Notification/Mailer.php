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

use CDash\Config;
use CDash\Log;
use CDash\Messaging\Notification\Email\EmailMessage;
use CDash\Messaging\Notification\Email\Mail;
use CDash\Model\BuildEmail;
use CDash\Singleton;

/**
 * Class Mailer
 * @package CDash\Messaging\Notification
 */
class Mailer extends Singleton
{
    /**
     * @param NotificationCollection $notifications
     * @throws \Exception
     */
    public static function send(NotificationCollection $notifications)
    {
        $mailer = self::getInstance();
        foreach ($notifications as $notification) {
            $mailer->sendNotification($notification);
        }
    }

    /**
     * @param NotificationInterface|EmailMessage $notification
     * @throws \Exception
     */
    public function sendNotification(NotificationInterface $notification)
    {
        $type = get_class($notification);
        switch ($type) {
            case EmailMessage::class:
                $status = Mail::to($notification->getRecipient())
                    ->send($notification);
                break;
            default:
                $message = "Unrecognized message type [{$type}]";
                throw new \Exception($message);
        }

        $config = Config::getInstance();
        BuildEmail::Log($notification, $status);

        // TODO: remove config check after integration test refactors
        if ($status || $config->get('CDASH_TESTING_MODE')) {
            BuildEmail::SaveNotification($notification);
        }
    }
}

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

namespace CDash\Middleware\Queue;

require_once dirname(__DIR__) . '/../../include/do_submit.php';

use Bernard\Message;
use Bernard\Message\DefaultMessage;
use CDash\Log;
use CDash\Middleware\Queue;

class SubmissionService
{
    /** @var string - The name of this service */
    const NAME = 'DoSubmit';

    /** @var string[] - Fields required for processing */
    protected static $required = ['file', 'project', 'checksum', 'md5'];

    /**
     * Returns a submission message for Queue::produce
     *
     * @param array $parameters
     * @return DefaultMessage
     * @throws \Exception
     */
    public static function createMessage(array $parameters)
    {
        $missing = [];
        foreach (self::$required as $required) {
            if (in_array($required, $parameters)) {
                continue;
            }
            $missing[] = $required;
        }
        if (!empty($missing)) {
            $plural = count($missing) > 1 ? 's' : '';
            $missingStr = implode(', ', $missing);
            $message = sprintf(
                'Cannot create message: Missing parameter%s: %s',
                $plural,
                $missingStr
            );
            throw new \Exception($message);
        }
        return new DefaultMessage(self::NAME, $parameters);
    }

    /**
     * Returns the name of the service in the format required by Queue::consume
     *
     * @return string
     */
    public function getConsumerName()
    {
        preg_match_all('/[A-Z][a-z]+/', static::NAME, $words);
        $concat = function ($prev, $word) {
            if (is_null($prev)) {
                return $word;
            }
            return strtolower("{$prev}-{$word}");
        };

        return array_reduce($words[0], $concat);
    }

    /**
     * Handles the incoming message
     *
     * @param Message $message
     * @return void
     * @throws \Exception
     */
    public function doSubmit(Message $message)
    {
        try {
            $fh = fopen($message->file, 'r');
            do_submit($fh, $message->project, $message->md5, $message->checksum);
        } catch (\Exception $e) {
            Log::getInstance()->error($e);
            throw $e;
        }
    }


    /**
     * Registers this service with a Queue
     *
     * @param Queue $queue
     * @return void
     */
    public function register(Queue $queue)
    {
        $queue->addService(self::NAME, $this);
    }
}

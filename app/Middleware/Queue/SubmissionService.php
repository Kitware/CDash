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
use Bernard\Message\PlainMessage;
use CDash\Config;
use CDash\Log;
use CDash\Middleware\Queue;

/**
 * Class SubmissionService
 * @package CDash\Middleware\Queue
 *
 * Usage:
 *
 * The queue package, Bernard, used by CDash has the notion of consumers of queues and producers
 * of messages for queues. SubmissionService is an implementation of a Bernard consumer that
 * also statically creates a message for a queue regarding a CTest submission in the format that it
 * expects (via SubmissionService::createMessage).
 *
 * By default we expect that the name of your queue will be 'do-submit'.
 * If you wish to provide your own queue name you should do the following:
 *
 *   1. Edit <cdash root>/config/queue.php.  Make sure this file is properly configured to be
 *      able to interact with your specific queue driver.
 *
 *   2. Towards the beginning of this file you will see an entry for 'ctest_submission_queue'.
 *      Set this value to the name of your queue.
 *
 */
class SubmissionService
{
    /** @var string - The name of this service */
    const NAME = 'do-submit';

    /** @var string[] - Fields required for processing */
    protected static $required = ['file', 'project', 'checksum', 'md5', 'ip'];

    protected $backupFileName;
    protected $httpClient;
    protected $queueName;

    /**
     * Returns a submission message for Queue::produce
     *
     * @param array $parameters
     * @return PlainMessage
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
        $name = isset($parameters['queue_name']) ? $parameters['queue_name'] : self::NAME;
        return new PlainMessage($name, $parameters);
    }

    /**
     * SubmissionService constructor.
     * @param string|null $queueName
     */
    public function __construct($queueName = null)
    {
        $this->backupFileName = null;
        $this->httpClient = null;
        $this->queueName = $queueName ?: self::NAME;
    }

    /**
     * @param $name
     * @param $arguments
     * @throws \Exception
     */
    public function __call($name, $arguments)
    {
        if ($name === lcfirst($this->queueName)) {
            $message = $arguments[0];
            if ($this->doSubmit($message)) {
                $this->delete($message);
            }
        }
    }

    /**
     * Returns the name of the service in the format required by Queue::consume
     *
     * @return string
     */
    public function getConsumerName()
    {
        return $this->queueName;
    }

    /**
     * Handles the incoming message
     *
     * @param Message $message
     * @return bool
     * @throws \Exception
     */
    private function doSubmit(Message $message)
    {
        try {
            Config::getInstance()->set('CDASH_REMOTE_ADDR', $message->ip);

            if (Config::getInstance()->get('CDASH_REMOTE_PROCESSOR')) {
                // Remote execution, pass the filename and CDash will retrieve
                // its contents.
                $fh = basename($message->file);
            } else {
                // Local execution, process an open file.
                $fh = fopen($message->file, 'r');
            }
            $handler = do_submit($fh, $message->project, null, $message->md5, $message->checksum);
            if (is_object($handler) && property_exists($handler, 'backupFileName')) {
                $this->backupFileName = $handler->backupFileName;
            }
        } catch (\Exception $e) {
            Log::getInstance()->error($e);
            throw $e;
        }
        return true;
    }

    /**
     * Request that the web server delete or achive a submission file.
     *
     * @param Message $message
     * @return void
     */
    public function delete(Message $message)
    {
        if (!Config::getInstance()->get('CDASH_REMOTE_PROCESSOR')) {
            // A more descriptively named copy of this file should now
            // exist on the server if it is configured to save backups.
            if (file_exists($message->file)) {
                unlink($message->file);
            }
            return;
        }

        // When remotely executing we tell the server to delete the file
        // after it has been parsed.
        $query_args = ['filename' => basename($message->file)];
        if ($this->backupFileName) {
            // If the handler provided us with a descriptive backup filename
            // we pass this on to the server in case it is configured to rename
            // (rather than delete) processed submission files.
            $query_args['dest'] = basename($this->backupFileName);
        }
        $url = Config::getInstance()->get('CDASH_BASE_URL') .
            '/api/v1/deleteSubmissionFile.php';
        $this->getHttpClient()->request('DELETE', $url,
                ['query' => $query_args]);
    }

    public function getHttpClient()
    {
        if (!$this->httpClient) {
            $this->httpClient = new \GuzzleHttp\Client();
        }
        return $this->httpClient;
    }

    public function setHttpClient(\GuzzleHttp\ClientInterface $client)
    {
        $this->httpClient = $client;
    }

    public function setBackupFileName($filename)
    {
        $this->backupFileName = $filename;
    }

    /**
     * Registers this service with a Queue
     *
     * @param Queue $queue
     * @return void
     */
    public function register(Queue $queue)
    {
        $queue->addService($this->queueName, $this);
    }
}

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
 * Bernard has a naming convention that must be followed for consumption of queues. Queue names
 * have no spaces, and the first letter of each word in the name of the queue is capitalized. For
 * instance, in the general case of the SubmissionClass, one needs to create a queue named do-submit
 * which will result in the Bernard based queue name being DoSubmit, which, not coincidentally,
 * has the affect that when consuming a message, the consumer that has been registered with Bernard
 * (in this case SubmissionService) will call a method on that service named doSubmit. In summary:
 *
 *   1. If the name of the queue is do-submit
 *   2. The name provided to the message must be DoSubmit
 *   3. This requires that the consumer associated with the message have a method named doSubmit
 *
 * If you wish to provide your own queue name, for instance, drake-cdash, you would use this
 *  class in the following way:
 *
 *   1. Ensure that your configuration of the queue is correct. The queue configuration file is
 *      located at <cdash root>/config/queue.php.
 *
 *   2. Create a queue
 *      $queue = new Queue();
 *
 *   3. Register a consumer, this, with the queue
 *      $queue_name = 'DrakeCdash'; // Bernard naming convention for queue named drake-cdash
 *      $service = new SubmissionService($queue_name); // Notice we provide the optional name arg
 *      $queue->addService($queue_name, $service);
 *
 *   4. Create a message
 *      // required arguments
 *      $arguments_to_create_message = [
 *        'file' => </path/to/file/being/submitted/by/ctest>,
 *        'project' => <The name of the project of whom the file belongs to>,
 *        'md5' => <A hash of the file created by md5_file('/path/to/file')>,
 *        'checksum' => <A boolean indicating whether or not to create a checksum for the file>,
 *      ];
 *
 *      // optionally, to change the name of the queue used by SubmissionService
 *      $arguments_to_create_message['queue_name'] = $queue_name;
 *
 *      $message = SubmissionService::createMessage($arguments_to_create_message);
 *
 *   5. Produce the message (send it to the queue)
 *      $queue->produce($message);
 *
 */
class SubmissionService
{
    /** @var string - The name of this service */
    const NAME = 'DoSubmit';

    /** @var string[] - Fields required for processing */
    protected static $required = ['file', 'project', 'checksum', 'md5', 'ip'];

    protected $backupFileName;
    protected $httpClient;
    protected $localProcessing;
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
        $this->localProcessing = true;
        $this->queueName = $queueName;
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
            $this->localProcessing = file_exists($message->file);
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
     * @return bool
     * @throws \Exception
     */
    private function doSubmit(Message $message)
    {
        try {
            Config::getInstance()->set('CDASH_REMOTE_ADDR', $message->ip);

            if ($this->localProcessing) {
                // Local execution, process an open file.
                $fh = fopen($message->file, 'r');
            } else {
                // Remote execution, pass the filename and CDash will retrieve
                // its contents.
                $fh = basename($message->file);
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
        if ($this->localProcessing) {
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

    /**
     * Registers this service with a Queue
     *
     * @param Queue $queue
     * @return void
     */
    public function register(Queue $queue)
    {
        $name = $this->queueName ?: self::NAME;
        $queue->addService($name, $this);
    }
}

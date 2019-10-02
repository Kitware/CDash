<?php

namespace IronMQ;

use IronCore\IronCore;

/**
 * PHP client for IronMQ
 * IronMQ is a scalable, reliable, high performance message queue in the cloud.
 *
 * @link https://github.com/iron-io/iron_mq_php
 * @link http://www.iron.io/products/mq
 * @link http://dev.iron.io/
 * @version 2.0.0
 * @package IronMQPHP
 * @copyright Feel free to copy, steal, take credit for, or whatever you feel like doing with this code. ;)
 */
class IronMQ extends IronCore
{
    protected $client_version = '2.0.0';
    protected $client_name = 'iron_mq_php';
    protected $product_name = 'iron_mq';
    protected $default_values = array(
        'protocol'    => 'https',
        'host'        => 'mq-aws-us-east-1.iron.io',
        'port'        => '443',
        'api_version' => '1',
    );

    const LIST_QUEUES_PER_PAGE = 30;
    const GET_MESSAGE_TIMEOUT = 60;
    const GET_MESSAGE_WAIT = 0;  // Seconds to wait until request finds a Message (Max is 30)

    /**
     * @param string|array $config
     *        Array of options or name of config file.
     * Fields in options array or in config:
     *
     * Required:
     * - token
     * - project_id
     * Optional:
     * - protocol
     * - host
     * - port
     * - api_version
     */
    public function __construct($config = null)
    {
        $this->getConfigData($config);
        $this->url = "{$this->protocol}://{$this->host}:{$this->port}/{$this->api_version}/";
    }

    /**
     * Switch active project
     *
     * @param string $project_id Project ID
     *
     * @throws \InvalidArgumentException
     */
    public function setProjectId($project_id)
    {
        if (!empty($project_id))
        {
            $this->project_id = $project_id;
        }
        if (empty($this->project_id))
        {
            throw new \InvalidArgumentException("Please set project_id");
        }
    }

    /**
     * Get list of message queues
     *
     * @param int $page
     * @param int $per_page
     *
     * @return mixed
     * @throws \JSON_Exception
     */
    public function getQueues($page = 0, $per_page = self::LIST_QUEUES_PER_PAGE)
    {
        $url = "projects/{$this->project_id}/queues";
        $params = array();
        if ($page !== 0)
        {
            $params['page'] = (int)$page;
        }
        if ($per_page !== self::LIST_QUEUES_PER_PAGE)
        {
            $params['per_page'] = (int)$per_page;
        }
        $this->setJsonHeaders();

        return self::json_decode($this->apiCall(self::GET, $url, $params));
    }

    /**
     * Get information about queue.
     * Also returns queue size.
     *
     * @param string $queue_name
     *
     * @return mixed
     */
    public function getQueue($queue_name)
    {
        $queue = rawurlencode($queue_name);
        $url = "projects/{$this->project_id}/queues/$queue";
        $this->setJsonHeaders();

        return self::json_decode($this->apiCall(self::GET, $url));
    }

    /**
     * Clear all messages from queue.
     *
     * @param string $queue_name
     *
     * @return mixed
     */
    public function clearQueue($queue_name)
    {
        $queue = rawurlencode($queue_name);
        $url = "projects/{$this->project_id}/queues/$queue/clear";
        $this->setJsonHeaders();

        return self::json_decode($this->apiCall(self::POST, $url));
    }

    /**
     * Push a message on the queue
     *
     * Examples:
     * <code>
     * $ironmq->postMessage("test_queue", "Hello world");
     * </code>
     * <code>
     * $ironmq->postMessage("test_queue", "Test Message", array(
     *   'timeout' => 120,
     *   'delay' => 2,
     *   'expires_in' => 2*24*3600 # 2 days
     * ));
     * </code>
     *
     * @param string $queue_name Name of the queue.
     * @param string $message
     * @param array $properties
     *
     * @return mixed
     */
    public function postMessage($queue_name, $message, $properties = array())
    {
        $msg = new IronMQMessage($message, $properties);
        $req = array(
            "messages" => array($msg->asArray())
        );
        $this->setCommonHeaders();
        $queue = rawurlencode($queue_name);
        $url = "projects/{$this->project_id}/queues/$queue/messages";
        $res = $this->apiCall(self::POST, $url, $req);
        $decoded = self::json_decode($res);
        $decoded->id = $decoded->ids[0];

        return $decoded;
    }

    /**
     * Push multiple messages on the queue
     *
     * Example:
     * <code>
     * $ironmq->postMessages("test_queue", array("Lorem", "Ipsum"), array(
     *   'timeout' => 120,
     *   'delay' => 2,
     *   'expires_in' => 2*24*3600 # 2 days
     * ));
     * </code>
     *
     * @param string $queue_name Name of the queue.
     * @param array $messages array of messages, each message same as for postMessage() method
     * @param array $properties array of message properties, applied to each message in $messages
     *
     * @return mixed
     */
    public function postMessages($queue_name, $messages, $properties = array())
    {
        $req = array(
            "messages" => array()
        );
        foreach ($messages as $message)
        {
            $msg = new IronMQMessage($message, $properties);
            array_push($req['messages'], $msg->asArray());
        }
        $this->setCommonHeaders();
        $queue = rawurlencode($queue_name);
        $url = "projects/{$this->project_id}/queues/$queue/messages";
        $res = $this->apiCall(self::POST, $url, $req);

        return self::json_decode($res);
    }

    /**
     * Get multiplie messages from queue
     *
     * @param string $queue_name Queue name
     * @param int $count
     * @param int $timeout
     * @param int $wait
     *
     * @return array|null array of messages or null
     */
    public function getMessages($queue_name, $count = 1, $timeout = self::GET_MESSAGE_TIMEOUT, $wait = self::GET_MESSAGE_WAIT)
    {
        $queue = rawurlencode($queue_name);
        $url = "projects/{$this->project_id}/queues/$queue/messages";
        $params = array();
        if ($count !== 1)
        {
            $params['n'] = (int)$count;
        }
        if ($timeout !== self::GET_MESSAGE_TIMEOUT)
        {
            $params['timeout'] = (int)$timeout;
        }
        if ($wait !== 0)
        {
            $params['wait'] = (int)$wait;
        }
        $this->setJsonHeaders();
        $response = $this->apiCall(self::GET, $url, $params);
        $result = self::json_decode($response);
        if (count($result->messages) < 1)
        {
            return null;
        }
        else
        {
            return $result->messages;
        }
    }

    /**
     * Get single message from queue
     *
     * @param string $queue_name Queue name
     * @param int $timeout
     * @param int $wait
     *
     * @return mixed|null single message or null
     */
    public function getMessage($queue_name, $timeout = self::GET_MESSAGE_TIMEOUT, $wait = self::GET_MESSAGE_WAIT)
    {
        $messages = $this->getMessages($queue_name, 1, $timeout, $wait);
        if ($messages)
        {
            return $messages[0];
        }
        else
        {
            return null;
        }
    }

    /**
     * Get the message with the given id.
     *
     * @param string $queue_name Queue name
     * @param string $message_id Message ID
     *
     * @return mixed
     */
    public function getMessageById($queue_name, $message_id)
    {
        $this->setCommonHeaders();
        $queue = rawurlencode($queue_name);
        $url = "projects/{$this->project_id}/queues/$queue/messages/{$message_id}";

        return self::json_decode($this->apiCall(self::GET, $url));
    }

    /**
     * Delete a Message from a Queue
     * This call will delete the message. Be sure you call this after you’re done with a message,
     * or it will be placed back on the queue.
     *
     * @param $queue_name
     * @param $message_id
     *
     * @return mixed
     */
    public function deleteMessage($queue_name, $message_id)
    {
        $this->setCommonHeaders();
        $queue = rawurlencode($queue_name);
        $url = "projects/{$this->project_id}/queues/$queue/messages/{$message_id}";

        return $this->apiCall(self::DELETE, $url);
    }

    /**
     * Delete Messages from a Queue
     * This call will delete the messages. Be sure you call this after you’re done with a message,
     * or it will be placed back on the queue.
     *
     * @param $queue_name
     * @param $message_ids
     *
     * @return mixed
     */
    public function deleteMessages($queue_name, $message_ids)
    {
        $req = array(
            "ids" => array()
        );
        foreach ($message_ids as $message_id)
        {
            array_push($req['ids'], $message_id);
        }
        $this->setCommonHeaders();
        $queue = rawurlencode($queue_name);
        $url = "projects/{$this->project_id}/queues/$queue/messages";
        $result = $this->apiCall(self::DELETE, $url, $req);

        return self::json_decode($result);
    }

    /**
     * Peek Messages on a Queue
     * Peeking at a queue returns the next messages on the queue, but it does not reserve them.
     *
     * @param string $queue_name
     *
     * @return object|null  message or null if queue is empty
     */
    public function peekMessage($queue_name)
    {
        $messages = $this->peekMessages($queue_name, 1);
        if ($messages == null)
        {
            return null;
        }
        else
        {
            return $messages[0];
        }
    }

    /**
     * Peek Messages on a Queue
     * Peeking at a queue returns the next messages on the queue, but it does not reserve them.
     *
     * @param string $queue_name
     * @param int $count The maximum number of messages to peek. Maximum is 100.
     *
     * @return array|null array of messages or null if queue is empty
     */
    public function peekMessages($queue_name, $count)
    {
        $queue = rawurlencode($queue_name);
        $url = "projects/{$this->project_id}/queues/$queue/messages/peek";
        $params = array();
        if ($count !== 1)
        {
            $params['n'] = (int)$count;
        }
        $this->setJsonHeaders();
        $response = self::json_decode($this->apiCall(self::GET, $url, $params));

        return $response->messages;
    }

    /**
     * Touch a Message on a Queue
     * Touching a reserved message extends its timeout by the duration specified when the message was created,
     * which is 60 seconds by default.
     *
     * @param string $queue_name
     * @param string $message_id
     *
     * @return mixed
     */
    public function touchMessage($queue_name, $message_id)
    {
        $this->setJsonHeaders();
        $queue = rawurlencode($queue_name);
        $url = "projects/{$this->project_id}/queues/$queue/messages/{$message_id}/touch";

        return self::json_decode($this->apiCall(self::POST, $url));
    }

    /**
     * Release a Message on a Queue
     * Releasing a reserved message unreserves the message and puts it back on the queue,
     * as if the message had timed out.
     *
     * @param string $queue_name
     * @param string $message_id
     * @param int $delay The item will not be available on the queue until this many seconds have passed.
     *                   Default is 0 seconds. Maximum is 604,800 seconds (7 days).
     *
     * @return mixed
     */
    public function releaseMessage($queue_name, $message_id, $delay = 0)
    {
        $this->setJsonHeaders();
        $queue = rawurlencode($queue_name);
        $params = array();
        if ($delay !== 0)
        {
            $params['delay'] = (int)$delay;
        }
        $url = "projects/{$this->project_id}/queues/$queue/messages/{$message_id}/release";

        return self::json_decode($this->apiCall(self::POST, $url, $params));
    }

    /**
     * Add alerts to a queue. This is for Pull Queue only.
     *
     * @param string $queue_name
     * @param array $alerts_hash
     *
     * @return mixed
     */
    public function addAlerts($queue_name, $alerts_hash)
    {
        $this->setJsonHeaders();
        $queue = rawurlencode($queue_name);
        $url = "projects/{$this->project_id}/queues/$queue/alerts";
        $options = array(
            'alerts' => $alerts_hash
        );

        return self::json_decode($this->apiCall(self::POST, $url, $options));
    }

    /**
     * Replace current queue alerts with a given list of alerts. This is for Pull Queue only.
     *
     * @param string $queue_name
     * @param array $alerts_hash
     *
     * @return mixed
     */
    public function updateAlerts($queue_name, $alerts_hash)
    {
        $this->setJsonHeaders();
        $queue = rawurlencode($queue_name);
        $url = "projects/{$this->project_id}/queues/$queue/alerts";
        $options = array(
            'alerts' => $alerts_hash
        );

        return self::json_decode($this->apiCall(self::PUT, $url, $options));
    }

    /**
     * Remove alerts from a queue. This is for Pull Queue only.
     *
     * @param string $queue_name
     * @param array $alerts_ids
     *
     * @return mixed
     */
    public function deleteAlerts($queue_name, $alerts_ids)
    {
        $this->setJsonHeaders();
        $queue = rawurlencode($queue_name);
        $url = "projects/{$this->project_id}/queues/$queue/alerts";
        $options = array(
            'alerts' => $alerts_ids
        );
        print_r(json_encode($options));

        return self::json_decode($this->apiCall(self::DELETE, $url, $options));
    }

    /**
     * Remove alert from a queue by its ID. This is for Pull Queue only.
     *
     * @param string $queue_name
     * @param string $alert_id
     *
     * @return mixed
     */
    public function deleteAlertById($queue_name, $alert_id)
    {
        $this->setJsonHeaders();
        $queue = rawurlencode($queue_name);
        $url = "projects/{$this->project_id}/queues/$queue/alerts/$alert_id";

        return self::json_decode($this->apiCall(self::DELETE, $url));
    }

    /**
     * Delete a Message Queue
     * This call deletes a message queue and all its messages.
     *
     * @param string $queue_name
     *
     * @return mixed
     */
    public function deleteQueue($queue_name)
    {
        $this->setJsonHeaders();
        $queue = rawurlencode($queue_name);
        $url = "projects/{$this->project_id}/queues/$queue";

        return self::json_decode($this->apiCall(self::DELETE, $url));
    }

    /**
     * Updates the queue object
     *
     * @param string $queue_name
     * @param array $options Parameters to change. keys:
     * - "subscribers" url's to subscribe to
     * - "push_type" multicast (default) or unicast.
     * - "retries" Number of retries. 3 by default
     * - "retries_delay" Delay between retries. 60 (seconds) by default
     *
     * @return mixed
     * @throws \JSON_Exception
     */
    public function updateQueue($queue_name, $options)
    {
        $this->setJsonHeaders();
        $queue = rawurlencode($queue_name);
        $url = "projects/{$this->project_id}/queues/$queue";

        return self::json_decode($this->apiCall(self::POST, $url, $options));
    }

    /**
     * Add Subscriber to a Queue
     *
     * Example:
     * <code>
     * $ironmq->addSubscriber("test_queue", array("url" => "http://example.com"));
     * </code>
     *
     * @param string $queue_name
     * @param array $subscriber_hash Subscriber. keys:
     * - "url" Subscriber url
     *
     * @return mixed
     */
    public function addSubscriber($queue_name, $subscriber_hash)
    {
        $this->setJsonHeaders();
        $queue = rawurlencode($queue_name);
        $url = "projects/{$this->project_id}/queues/$queue/subscribers";
        $options = array(
            'subscribers' => array($subscriber_hash)
        );

        return self::json_decode($this->apiCall(self::POST, $url, $options));
    }

    /**
     * Remove Subscriber from a Queue
     *
     * Example:
     * <code>
     * $ironmq->removeSubscriber("test_queue", array("url" => "http://example.com"));
     * </code>
     *
     * @param string $queue_name
     * @param array $subscriber_hash Subscriber. keys:
     * - "url" Subscriber url
     *
     * @return mixed
     */
    public function removeSubscriber($queue_name, $subscriber_hash)
    {
        $this->setJsonHeaders();
        $queue = rawurlencode($queue_name);
        $url = "projects/{$this->project_id}/queues/$queue/subscribers";
        $options = array(
            'subscribers' => array($subscriber_hash)
        );

        return self::json_decode($this->apiCall(self::DELETE, $url, $options));
    }

    /**
     * Get Message's Push Statuses (for Push Queues only)
     *
     * Example:
     * <code>
     * statuses = $ironmq->getMessagePushStatuses("test_queue", $message_id)
     * </code>
     *
     * @param string $queue_name
     * @param string $message_id
     *
     * @return array
     */
    public function getMessagePushStatuses($queue_name, $message_id)
    {
        $this->setJsonHeaders();
        $queue = rawurlencode($queue_name);
        $url = "projects/{$this->project_id}/queues/$queue/messages/{$message_id}/subscribers";
        $response = self::json_decode($this->apiCall(self::GET, $url));

        return $response->subscribers;
    }

    /**
     * Delete Message's Push Status (for Push Queues only)
     *
     * Example:
     * <code>
     * $ironmq->deleteMessagePushStatus("test_queue", $message_id, $subscription_id)
     * </code>
     *
     * @param string $queue_name
     * @param string $message_id
     * @param string $subscription_id
     *
     * @return mixed
     */
    public function deleteMessagePushStatus($queue_name, $message_id, $subscription_id)
    {
        $this->setJsonHeaders();
        $queue = rawurlencode($queue_name);
        $url = "projects/{$this->project_id}/queues/$queue/messages/{$message_id}/subscribers/{$subscription_id}";

        return self::json_decode($this->apiCall(self::DELETE, $url));
    }

    private function setJsonHeaders()
    {
        $this->setCommonHeaders();
    }

    private function setPostHeaders()
    {
        $this->setCommonHeaders();
        $this->headers['Content-Type'] = 'multipart/form-data';
    }
}

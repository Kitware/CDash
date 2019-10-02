<?php

namespace IronMQ;

/**
 * IronMQMessage
 * @package IronMQ
 * @author Tino Ehrich (tino@bigpun.me)
 */
class IronMQMessage
{
    const MAX_EXPIRES_IN = 2592000;

    /**
     * @var string
     */
    private $body;

    /**
     * @var int
     */
    private $timeout;

    /**
     * @var int
     */
    private $delay;

    /**
     * @var int
     */
    private $expires_in;

    /**
     * Create a new message.
     *
     * @param string $message A message body
     * @param array $properties An array of message properties
     *
     * Fields in $properties array:
     * - timeout: Timeout, in seconds. After timeout, item will be placed back on queue. Defaults to 60.
     * - delay: The item will not be available on the queue until this many seconds have passed. Defaults to 0.
     * - expires_in: How long, in seconds, to keep the item on the queue before it is deleted.
     *               Defaults to 604800 (7 days). Maximum is 2592000 (30 days).
     */
    public function __construct($message, $properties = array())
    {
        $this->setBody($message);

        if (array_key_exists("timeout", $properties))
        {
            $this->setTimeout($properties['timeout']);
        }
        if (array_key_exists("delay", $properties))
        {
            $this->setDelay($properties['delay']);
        }
        if (array_key_exists("expires_in", $properties))
        {
            $this->setExpiresIn($properties['expires_in']);
        }
    }

    /**
     * @return string
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * @param string $body
     *
     * @return $this
     */
    public function setBody($body)
    {
        if (empty($body))
        {
            throw new \InvalidArgumentException("Please specify a body");
        }

        $this->body = (string)$body;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getTimeout()
    {
        # 0 is considered empty, but we want people to be able to set a timeout of 0
        if (!empty($this->timeout) || $this->timeout === 0)
        {
            return $this->timeout;
        }

        return null;
    }

    /**
     * @param int $timeout
     *
     * @return $this
     */
    public function setTimeout($timeout)
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getDelay()
    {
        # 0 is considered empty, but we want people to be able to set a delay of 0
        if (!empty($this->delay) || $this->delay == 0)
        {
            return $this->delay;
        }

        return null;
    }

    /**
     * @param int $delay
     *
     * @return $this
     */
    public function setDelay($delay)
    {
        $this->delay = $delay;

        return $this;
    }

    /**
     * @return int
     */
    public function getExpiresIn()
    {
        return $this->expires_in;
    }

    /**
     * @param $expires_in
     *
     * @return $this
     */
    public function setExpiresIn($expires_in)
    {
        if ($expires_in > self::MAX_EXPIRES_IN)
        {
            throw new \InvalidArgumentException("Expires In can't be greater than " . self::MAX_EXPIRES_IN . ".");
        }

        $this->expires_in = $expires_in;

        return $this;
    }

    /**
     * @return array
     */
    public function asArray()
    {
        $array = array(
            'body' => $this->getBody(),
        );

        if ($this->getTimeout() !== null)
        {
            $array['timeout'] = $this->getTimeout();
        }
        if ($this->getDelay() !== null)
        {
            $array['delay'] = $this->getDelay();
        }
        if ($this->getExpiresIn() !== null)
        {
            $array['expires_in'] = $this->getExpiresIn();
        }

        return $array;
    }
}
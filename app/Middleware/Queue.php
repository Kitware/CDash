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

namespace CDash\Middleware;

use Bernard\Driver;
use Bernard\Message;
use Bernard\Middleware;
use Bernard\Middleware\MiddlewareBuilder;
use Bernard\Producer;
use Bernard\QueueFactory;
use Bernard\QueueFactory\PersistentFactory;
use Bernard\Router;
use Bernard\Router\SimpleRouter;
use Bernard\Serializer;
use Bernard\Serializer\SimpleSerializer;
use CDash\Middleware\Queue\Consumer;
use CDash\Middleware\Queue\DriverFactory;

/**
 * Class Queue
 * @package CDash\Middleware
 * @see https://github.com/bernardphp/bernard/blob/c452caa6de208b0274449cde1c48eb32fb9f59f9/example/bootstrap.php
 */
class Queue
{
    /** @var Driver $driver */
    protected $driver;

    /** @var Serializer $serializer */
    protected $serializer;

    /** @var MiddlewareBuilder $middlewareBuilder */
    protected $middlewareBuilder;

    /** @var Middleware\ErrorLogFactory $errorLogFactory */
    protected $errorLogFactory;

    /** @var Middleware\FailuresFactory $failuresFactory */
    protected $failuresFactory;

    /** @var  QueueFactory $queueFactory*/
    protected $queueFactory;

    /** @var Producer $producer */
    protected $producer;

    /** @var  Router $router*/
    protected $router;

    /** @var Consumer $consumer */
    protected $consumer;

    /** @var  */
    protected $services;

    /**
     * Queue constructor.
     * @param Driver $driver
     */
    public function __construct(Driver $driver = null, array $services = [])
    {
        $this->driver = $driver;
        $this->services = $services;
    }

    /**
     * @return Driver|Driver\AppEngineDriver|Driver\DoctrineDriver|Driver\FlatFileDriver|Driver\PhpRedisDriver|Driver\PredisDriver|Driver\SqsDriver|null
     * @throws \Doctrine\DBAL\DBALException
     */
    protected function getDriver()
    {
        if (!$this->driver) {
            $this->driver = DriverFactory::create();
        }
        return $this->driver;
    }

    /**
     * @return Serializer|SimpleSerializer
     */
    protected function getSerializer()
    {
        if (!$this->serializer) {
            $this->serializer = new SimpleSerializer();
        }
        return $this->serializer;
    }

    /**
     * @return MiddlewareBuilder
     */
    protected function getMiddlewareBuilder()
    {
        if (!$this->middlewareBuilder) {
            $this->middlewareBuilder = new MiddlewareBuilder();
        }
        return $this->middlewareBuilder;
    }

    /**
     * @return Middleware\ErrorLogFactory
     */
    protected function getErrorLogFactory()
    {
        if (!$this->errorLogFactory) {
            $this->errorLogFactory = new Middleware\ErrorLogFactory();
        }
        return $this->errorLogFactory;
    }

    /**
     * @return Middleware\FailuresFactory
     */
    protected function getFailuresFactory()
    {
        if (!$this->failuresFactory) {
            $this->failuresFactory = new Middleware\FailuresFactory($this->getQueueFactory());
        }
        return $this->failuresFactory;
    }

    /**
     * @return MiddlewareBuilder
     */
    protected function getConsumerMiddleware()
    {
        $chain = $this->getMiddlewareBuilder();
        $chain->push($this->getErrorLogFactory());
        $chain->push($this->getFailuresFactory());

        return $chain;
    }

    /**
     * @return QueueFactory|PersistentFactory
     */
    protected function getQueueFactory()
    {
        if (!$this->queueFactory) {
            $this->queueFactory = new PersistentFactory($this->getDriver(), $this->getSerializer());
        }
        return $this->queueFactory;
    }

    /**
     * @return Producer
     */
    protected function getProducer()
    {
        if (!$this->producer) {
            $this->producer = new Producer($this->getQueueFactory(), $this->getMiddlewareBuilder());
        }
        return $this->producer;
    }

    /**
     * @return Router|SimpleRouter
     */
    protected function getRouter()
    {
        if (!$this->router) {
            $this->router = new SimpleRouter($this->services);
        }
        return $this->router;
    }

    /**
     * @return Consumer
     */
    protected function getConsumer()
    {
        if (!$this->consumer) {
            $this->consumer = new Consumer($this->getRouter(), $this->getConsumerMiddleware());
        }
        return $this->consumer;
    }

    /**
     * @param Message $message
     */
    public function produce(Message $message)
    {
        $queue = $this->getProducer();
        $queue->produce($message);
    }

    /**
     * @param $name
     * @param array $options
     */
    public function consume($name, array $options = [])
    {
        $queues = $this->getQueueFactory();
        $consumer = $this->getConsumer();

        $consumer->consume($queues->create($name), $options);
    }

    /**
     * @param $name
     * @param $service
     */
    public function addService($name, $service)
    {
        $this->services[$name] = $service;
    }
}

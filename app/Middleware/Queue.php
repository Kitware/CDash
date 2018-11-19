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
use Bernard\EventListener;
use Bernard\Message;
use Bernard\Producer;
use Bernard\QueueFactory;
use Bernard\QueueFactory\PersistentFactory;
use Bernard\Router;
use Bernard\Router\SimpleRouter;
use Bernard\Serializer;
use Symfony\Component\EventDispatcher\EventDispatcher;

use CDash\Middleware\Queue\Consumer;
use CDash\Middleware\Queue\DriverFactory;

/**
 * Class Queue
 * @package CDash\Middleware
 * @see https://github.com/bernardphp/bernard/blob/1.0.0-alpha9/example/bootstrap.php
 */
class Queue
{
    /** @var Driver $driver */
    protected $driver;

    /** @var Serializer $serializer */
    protected $serializer;

    /** @var EventDispatcher $eventDispatcher */
    protected $eventDispatcher;

    /** @var EventListener\ErrorLogSubscriber $errorLogSubscriber */
    protected $errorLogSubscriber;

    /** @var EventListener\FailureSubscriber $failureSubscriber */
    protected $failureSubscriber;

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

        // Because of the way FailureSubscriber works, we need a producer even
        // if we're only consuming messages. That means we need to initialize
        // all these member objects in the constructor.
        $this->serializer = new Serializer();
        $this->queueFactory = new PersistentFactory($this->getDriver(), $this->serializer);
        $this->eventDispatcher = new EventDispatcher();
        $this->producer = new Producer($this->queueFactory, $this->eventDispatcher);

        $this->errorLogSubscriber = new EventListener\ErrorLogSubscriber();
        $this->eventDispatcher->addSubscriber($this->errorLogSubscriber);

        $this->failureSubscriber = new EventListener\FailureSubscriber($this->producer);
        $this->eventDispatcher->addSubscriber($this->failureSubscriber);
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
    public function getConsumer()
    {
        if (!$this->consumer) {
            $this->consumer = new Consumer($this->getRouter(), $this->eventDispatcher);
        }
        return $this->consumer;
    }

    /**
     * @param Message $message
     */
    public function produce(Message $message)
    {
        $this->producer->produce($message, $message->queueName);
    }

    /**
     * @param $name
     * @param array $options
     */
    public function consume($name, array $options = [])
    {
        $consumer = $this->getConsumer();
        $consumer->consume($this->queueFactory->create($name), $options);
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

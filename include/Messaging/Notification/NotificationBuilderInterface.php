<?php
namespace CDash\Messaging\Notification;

use CDash\Collection\CollectionInterface;
use CDash\Messaging\FactoryInterface;

interface NotificationBuilderInterface
{
    /**
     * NotificationBuilderInterface constructor.
     * @param FactoryInterface $factory
     * @param CollectionInterface $collection
     */
    public function __construct(FactoryInterface $factory, CollectionInterface $collection);

    /**
     * @return void
     */
    public function createNotification();

    /**
     * @return void
     */
    public function addPreamble();

    /**
     * @return void
     */
    public function addSummary();

    /**
     * @return void
     */
    public function addTopics();

    /**
     * @return void
     */
    public function addSubject();


    /**
     * @return void
     */
    public function addDeliveryInformation();
}

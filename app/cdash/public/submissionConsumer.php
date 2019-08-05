<?php
require dirname(__DIR__) . '/config/config.php';

use CDash\Database;
use CDash\Middleware\Queue;
use CDash\Middleware\Queue\SubmissionService;

// Configure PDO to throw an exception if any SQL errors occur while
// processing submissions.
Database::getInstance()->getPdo()->setAttribute(
        PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$service_container = \CDash\ServiceContainer::getInstance();

/** @var Queue $queue */
$queue = $service_container->create(Queue::class);

$queue_config = \CDash\Config::getInstance()->load('queue');
$container = $service_container->getContainer();
$submission_service = $container->make(
    SubmissionService::class,
    ['queueName' => $queue_config['ctest_submission_queue']]
);
$submission_service->register($queue);

try {
    $queue->consume($submission_service->getConsumerName(), ['stop-on-error' => true]);
} catch (\Exception $e) {
    // Exit gracefully if an exception occurs.
    $queue->getConsumer()->shutdown();
    exit(1);
}

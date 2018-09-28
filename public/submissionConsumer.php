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

/** @var SubmissionService $submission_service */
$submission_service = $service_container->create(SubmissionService::class);

$submission_service->register($queue);

try {
    $queue->consume($submission_service->getConsumerName(), ['stop-on-error' => true]);
} catch (\Exception $e) {
    // Exit gracefully if an exception occurs.
    $queue->getConsumer()->shutdown();
    exit(1);
}

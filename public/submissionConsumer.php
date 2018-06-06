<?php
require dirname(__DIR__) . '/config/config.php';

use CDash\Middleware\Queue;
use CDash\Middleware\Queue\SubmissionService;

$service_container = \CDash\ServiceContainer::getInstance();

/** @var Queue $queue */
$queue = $service_container->create(Queue::class);

/** @var SubmissionService $submission_service */
$submission_service = $service_container->create(SubmissionService::class);

$submission_service->register($queue);
$queue->consume($submission_service->getConsumerName(), ['max-runtime' => 0.1]);

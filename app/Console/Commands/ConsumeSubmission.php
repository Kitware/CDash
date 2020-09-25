<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use CDash\Database;
use CDash\Middleware\Queue;
use CDash\Middleware\Queue\SubmissionService;

class ConsumeSubmission extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'submission:consume
                            {--one-shot : Stop the consumer once the queue is empty}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Start a submission consumer worker';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        // Configure PDO to throw an exception if any SQL errors occur while
        // processing submissions.
        Database::getInstance()->getPdo()->setAttribute(
                \PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

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
            $options = ['stop-on-error' => true];
            if ($this->option('one-shot')) {
                $options['stop-when-empty'] = true;
            }
            $queue->consume($submission_service->getConsumerName(), $options);
        } catch (\Exception $e) {
            // Exit gracefully if an exception occurs.
            $queue->getConsumer()->shutdown();
            exit(1);
        }
    }
}

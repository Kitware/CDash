<?php

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/config/config.php';
require_once 'include/pdo.php';
include 'include/do_submit.php';


use Bernard\Router\SimpleRouter;
use Bernard\Consumer;
use Bernard\QueueFactory\PersistentFactory;
use Bernard\Message\DefaultMessage;
use Bernard\Producer;
use Bernard\Serializer;
use Symfony\Component\EventDispatcher\EventDispatcher;

class CDashSubmissionService
{
    public function doSubmit(DefaultMessage $message)
    {
        global $CDASH_BASE_URL;

        $result = do_submit($message['buildsubmissionid'], $message['projectid'],
                            $message['expected_md5'], $message['do_checksum'], $message['submission_id']);

        // If the submission didn't explicitly fail, delete the submission XML to avoid
        // duplicate submissions
        if ($result !== false) {
             $client = new GuzzleHttp\Client();
             $response = $client->request('DELETE',
                                          $CDASH_BASE_URL . '/api/v1/deleteBuildSubmissionXml.php',
                                          array('query' => array('buildsubmissionid' => $message['buildsubmissiondid'])));
        }
    }
}

$router = new SimpleRouter();
$router->add('DoSubmit', new CDashSubmissionService);

$factory = new PersistentFactory($CDASH_BERNARD_DRIVER, new Serializer());

// Create a Consumer and start the loop.
$consumer = new Consumer($router, new EventDispatcher());
$consumer->consume($factory->create('do-submit'));
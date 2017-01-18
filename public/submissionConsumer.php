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
        global $CDASH_BASE_URL, $CDASH_REMOTE_ADDR;

        // Since this could be running on a remote machine, spoof the IP
        // to appear as the IP that actually submitted the build
        $CDASH_REMOTE_ADDR = $message->get('submission_ip');

        if ($message->has('coverage_submission') && $message->get('coverage_submission')) {
            $fileHandle = fileHandleFromSubmissionId($message->get('filename'), true);
            $result = do_submit($fileHandle, $message->get('projectid'), $message->get('md5'), false);
        } else {
            $result = do_submit($message->get('buildsubmissionid'), $message->get('projectid'),
                                $message->get('expected_md5'), $message->get('do_checksum'),
                                $message->get('submission_id'));
        }

        // If the submission didn't explicitly fail, delete the submission XML to avoid
        // duplicate submissions
        if ($result !== false) {
            $filename = array_key_exists('filename', $message) ?
                      $message->get('filename') : $message->get('buildsubmissionid') . '.xml';
            $client = new GuzzleHttp\Client();
            $response = $client->request('DELETE',
                                         $CDASH_BASE_URL . '/api/v1/deleteSubmissionFile.php',
                                         array('query' =>
                                               array('filename' => $filename)));
        }
    }
}

$router = new SimpleRouter();
$router->add('DoSubmit', new CDashSubmissionService);

$factory = new PersistentFactory($CDASH_BERNARD_DRIVER, new Serializer());

// Create a Consumer and start the loop.
$consumer = new Consumer($router, new EventDispatcher());
$consumer->consume($factory->create('do-submit'));

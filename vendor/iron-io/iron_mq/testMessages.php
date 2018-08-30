<?php

#require("phar://iron_mq.phar");
require __DIR__ . '/vendor/autoload.php';

$ironmq = new \IronMQ\IronMQ();
#$ironmq->debug_enabled = true;
$ironmq->ssl_verifypeer = false;

for ($i = 0; $i < 10; $i++)
{
    echo "Post message:\n";
    $res = $ironmq->postMessage("test_queue", "Test Message $i");
    var_dump($res);

    echo "Post messages:\n";
    $res = $ironmq->postMessages("test-queue-multi", array("Test Message $i", "Test Message $i-2"));
    var_dump($res);

    echo "Get message..\n";
    $message = $ironmq->getMessage("test_queue");
    print_r($message);

    echo "Touch message..\n";
    $res = $ironmq->touchMessage("test_queue", $message->id);
    print_r($res);

    echo "Release message..\n";
    $res = $ironmq->releaseMessage("test_queue", $message->id);
    print_r($res);

    echo "Peek message..\n";
    $res = $ironmq->peekMessage("test_queue");
    print_r($res);

    echo "Delete message..\n";
    $message = $ironmq->deleteMessage("test_queue", $message->id);
    print_r($message);

    $message = $ironmq->getMessage("test_queue");
    print_r($message);

    echo "Getting multiple messages..\n";
    $messageIds = array();
    $messages = $ironmq->getMessages("test-queue-multi", 2);
    foreach ($messages as $message)
    {
        array_push($messageIds, $message->id);
    }
    echo "Deleting messages with ids..\n";
    print_r($messageIds);
    $res = $ironmq->deleteMessages("test-queue-multi", $messageIds);
    print_r($res);

    echo "Adding alerts..\n";
    $res = $ironmq->postMessage("test_alert_queue", "Test Message 1");
    $first_alert = array(
        'type'      => 'fixed',
        'direction' => 'desc',
        'trigger'   => 1001,
        'snooze'    => 10,
        'queue'     => 'test_alert_queue'
    );
    $second_alert = array(
        'type'      => 'fixed',
        'direction' => 'asc',
        'trigger'   => 1000,
        'snooze'    => 5,
        'queue'     => 'test_alert_queue',
    );

    $res = $ironmq->addAlerts("test_alert_queue", array($first_alert, $second_alert));
    print_r($res);

    echo "Deleting alerts with ids..\n";
    $message = $ironmq->getQueue("test_alert_queue");
    $alert_ids = array();
    $alerts = $message->alerts;
    foreach ($alerts as $alert)
    {
        array_push($alert_ids, array('id' => $alert->id));
    }
    print_r($alert_ids);
    $res = $ironmq->deleteAlerts("test_alert_queue", $alert_ids);
    print_r($res);

    echo "\n------$i-------\n";
}


echo "\n done";

<?php

#require("phar://iron_mq.phar");
require __DIR__ . '/vendor/autoload.php';

$ironmq = new \IronMQ\IronMQ();
#$ironmq->debug_enabled = true;
$ironmq->ssl_verifypeer = false;

$queue_name = "push-queue-" . rand(0, 100);

$subscribers = array();
for ($i = 0; $i < 5; $i++)
{
    $subscribers[$i] = array(
        'url' => "http://rest-test.iron.io/code/200?store=$queue_name-$i"
    );
}


# enable push queue
$res = $ironmq->updateQueue($queue_name, array(
    'subscribers' => $subscribers,
    'push_type'   => "unicast"
));
#print_r($res);

$res = $ironmq->getQueue($queue_name);
echo "Queue enabled, " . count($res->subscribers) . " subscribers\n";

# Add one more subscriber
$res = $ironmq->addSubscriber($queue_name, array('url' => 'http://example.com'));
#print_r($res);


$res = $ironmq->getQueue($queue_name);
echo "Added subscriver, " . count($res->subscribers) . " subscribers\n";

$subscribers = $res->subscribers;
# Remove all subscribers
foreach ($subscribers as $subscriber)
{
    echo "- " . $subscriber->url . "\n";
    $ironmq->removeSubscriber($queue_name, array('url' => $subscriber->url));
}

$res = $ironmq->getQueue($queue_name);
echo "Queue info:\n";
print_r($res);

$res = $ironmq->deleteQueue($queue_name);
echo "Queue deleted:\n";
print_r($res);

echo "\n done";

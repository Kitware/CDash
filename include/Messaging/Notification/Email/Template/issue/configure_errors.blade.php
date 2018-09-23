<?php
use CDash\Messaging\Topic\Topic;
$max = $subscription->getProject()->EmailMaxChars;
$subscriber = $subscription->getSubscriber();
$topics = $subscriber->getTopics();
$configureTopic = $topics->get(Topic::CONFIGURE);
$configureCollection = $configureTopic->getTopicCollection();
$configure = $configureCollection->current();

$processed = trim(substr($configure->Log, 0, $max));
$lines = explode(PHP_EOL, $processed);
$lines = array_map(function ($idx, $line) {
    $line = trim($line);
    if ($idx) {
        $line = "        {$line}";
    }
    return $line;
}, array_keys($lines), $lines);
$log = implode("\n", $lines);
?>
Status: {{ $configure->Status }}
Output: {{ $log }}

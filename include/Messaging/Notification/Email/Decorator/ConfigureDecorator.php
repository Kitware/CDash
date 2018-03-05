<?php
namespace CDash\Messaging\Notification\Email\Decorator;

use CDash\Collection\Collection;
use CDash\Messaging\Topic\Topic;

class ConfigureDecorator extends Decorator
{
    private $template = "Status: {{ status }} ({{ url }})\nOutput: {{ log }}\n";

    public function setTopic(Topic $topic)
    {
        $collection = $topic->getTopicCollection();
        $configure = $collection->current();
        $log = $this->processLog($configure->Log);
        $data = [
            'status' => $configure->Status,
            'url' => $configure->getURL(),
            'log' => $log,
        ];

        $this->text = "*Configure*\n";
        $this->text .= $this->decorateWith($this->template, $data);
        return $this->text;
    }

    private function processLog(string $log)
    {
        $processed = trim(substr($log, 0, $this->maxChars));
        $lines = explode("\n", $processed);
        $lines = array_map(function ($idx, $line) {
            $line = trim($line);
            if ($idx) {
                $line = "        {$line}";
            }
            return $line;
        }, array_keys($lines), $lines);
        return implode("\n", $lines);
    }
}

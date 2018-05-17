<?php
namespace CDash\Messaging\Notification\Email\Decorator;

use CDash\Collection\Collection;
use CDash\Messaging\Topic\Topic;
use CDash\Model\Build;
use CDash\Model\BuildConfigure;

class ConfigureDecorator extends Decorator
{
    private $template = "Status: {{ status }} ({{ url }})\nOutput: {{ log }}\n";

    public function setTopic(Topic $topic)
    {
        $collection = $topic->getTopicCollection();
        /** @var BuildConfigure $configure */
        $configure = $collection->current();
        $log = $this->processLog($configure->Log);
        $builds = $topic->getBuildCollection();
        $id = null;

        // if the builds are subprojects we want to point to the url of the parent
        // which will display all of the subproject configurations (which themselves are
        // identical to one another).
        if ($builds->count() > 1) {
            /** @var Build $build */
            $builds->rewind();
            $build = $builds->current();
            $id = $build->GetParentId();
        }

        $data = [
            'status' => $configure->Status,
            'url' => $configure->getURL($id),
            'log' => $log,
        ];

        $this->text = "*Configure*\n";
        $this->text .= $this->decorateWith($this->template, $data);
        return $this->text;
    }

    private function processLog($log)
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

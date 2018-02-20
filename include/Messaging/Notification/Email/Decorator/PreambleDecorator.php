<?php
namespace CDash\Messaging\Notification\Email\Decorator;

class PreambleDecorator extends Decorator
{
    private $template = 'A submission to CDash for the project {{ project_name }} has '
                . '{{ topic_list }}. You have been identified as one of the authors '
                . 'who have checked in changes that are part of this submission '
                . 'or you are listed in the default contact list.' . "\n\n"
                . 'Details on the submission can be found at {{ project_url }}' . "\n";

    public function addSubject($subject)
    {
        $descriptions = array_column($subject['topics'], 'description');
        $data = [
            'project_name' => $subject['project_name'],
            'topic_list' => implode(' and ', array_map('strtolower', $descriptions)),
            'project_url' => $subject['project_url'],
        ];

        $this->text = $this->decorateWith($this->template, $data);
    }
}

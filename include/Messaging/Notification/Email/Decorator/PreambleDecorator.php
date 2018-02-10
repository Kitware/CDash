<?php
namespace CDash\Messaging\Notification\Email\Decorator;

class PreambleDecorator extends Decorator
{
    /**
     * @return string
     */
    protected function getTemplate()
    {
        $template = 'You have been identified as one of the authors '
            . 'who have checked in changes that are part of this submission '
            . 'or you are listed in the default contact list.' . "\n\n"
            . 'Details on the submission can be found at {{ url }}' . "\n";
        return $template;
    }
}

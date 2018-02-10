<?php
namespace CDash\archive\Messaging\Email\Decorator;

use CDash\Messaging\Email\EmailMessage;

class PreambleEmailDecorator extends EmailDecorator
{
    const BODY_TEMPLATE = [
        'A Submission to CDash for the project %s has %s.',
        'You have been identified as one of the authors who have checked in changes that are part of this submission or you are listed in the default contact list.',
        '',
        'Details on the submission can be found at %s.',
    ];

    const SUBJECT = 'FAILED';

    /**
     * This returns true if the decorator has content (its topic) to add to a message, e.g. errors,
     * false otherwise.
     * @return boolean
     */
    public function hasTopic()
    {
        // always include preamble
        return true;
    }

    /**
     * @return string
     */
    public function body()
    {

    }

    /**
     * @return string
     */
    public function subject()
    {
        return self::SUBJECT;
    }
}

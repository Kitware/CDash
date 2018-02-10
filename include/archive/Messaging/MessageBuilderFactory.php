<?php
namespace CDash\archive\Messaging;

use CDash\Messaging\Collection\BuildCollection;
use CDash\Messaging\Collection\DecoratorCollection;
use CDash\Messaging\Email\Decorator\BuildErrorsEmailDecorator;
use CDash\Messaging\Email\Decorator\MissingTestsEmailDecorator;
use CDash\Messaging\Email\EmailMessage;
use CDash\Messaging\Email\EmailDigestMessage;
use CDash\Messaging\Email\Decorator\BuildFailureErrorsEmailDecorator;
use CDash\Messaging\Email\Decorator\BuildFailureWarningsEmailDecorator;
use CDash\Messaging\Email\Decorator\BuildWarningsEmailDecorator;
use CDash\Messaging\Email\Decorator\ConfigureErrorsEmailDecorator;
use CDash\Messaging\Email\Decorator\DynamicAnalysisEmailDecorator;
use CDash\Messaging\Email\Decorator\TestFailuresEmailDecorator;
use CDash\Messaging\Email\Decorator\TestWarningsEmailDecorator;
use CDash\Messaging\Email\Decorator\UpdateErrorsEmailDecorator;
use CDash\Messaging\Email\EmailMessageBuilder;
use CDash\Messaging\Email\RecipientCollection;
use SendGrid\Email;

/**
 * MessageFactory
 *
 * There are 4 types of emails that CDash can send:
 *   * A per handler email:
 *     - Configure
 *     - Build
 *     - Test
 *     - Dynamic Analysis
 *     - Update
 *   * A summary email, which is simply a digest of all per handler emails created on the previous
 *     day
 *   * A broken update email, where only the site maintainer is notified, though the email is an
 *     UpdateHandler email
 *   * A fix email to notify a user of a problem that has been fixed
 *
 * The type of email that is sent is entirely dependant on preferences that can be set on any of
 * three following entities:
 *   * The project
 *   * The group (e.g. Nightly, Continuous, etc.)
 *   * The individual user account
 *
 * Furthermore there is the case where email addresses are obtained from an UpdateHandler where the
 * committer of that code is notified
 */
class MessageBuilderFactory
{

    /**
     * @param \ActionableBuildInterface $actionableBuild
     * @param string $messageType
     * @return MessageInterface
     * @throws \TypeError
     */
    public function createMessage(
        \ActionableBuildInterface $actionableBuild,
        $messageType = Message::TYPE_EMAIL
    )
    {
        switch($messageType)
        {
            case Message::TYPE_EMAIL:
                $builder = new EmailMessageBuilder($actionableBuild);
                break;
            default:
                throw new \TypeError('Message Type not recognized');
        }

        $director = new MessageDirector();

        return $director->build($builder);
    }
}

<?php
namespace CDash\archive\Messaging\Email;

use CDash\Messaging\Collection\BuildCollection;
use CDash\Messaging\Collection\DecoratorCollection;
use CDash\Messaging\Collection\RecipientCollection;
use CDash\Messaging\DecoratorInterface;
use CDash\Messaging\Email\Decorator\EmailDecoratorInterface;
use CDash\Messaging\Message;

/**
 * Class EmailMessage
 * @package CDash\Messaging\Email
 * TODO: change class name to NotifyEmailHandler
 */
class EmailMessage extends Message
{
    /** @var \UserProject[]  $subscribers */
    private $subscribers = []; // Subscribers need not be authors

    /** @var \UserProject[]  $authors */
    private $authors = []; // Authors need not be subscribers

    private $body;
    private $subject;
    private $messages = [];

    /**
     * @param EmailDecoratorInterface $decorator
     * @return EmailMessage
     */
    public function addDecorator(DecoratorInterface $decorator)
    {
        return $this->addEmailDecorator($decorator);
    }

    /**
     * @param EmailDecoratorInterface $decorator
     * @return $this
     */
    private function addEmailDecorator(EmailDecoratorInterface $decorator)
    {
        $decorator->setMessage($this);
        $this->decoratorCollection->add($decorator);
        return $this;
    }

    public function getBody()
    {
        $body = $this->body;
        /** @var EmailDecoratorInterface $decorate */
        foreach ($this->decoratorCollection as $decorate) {
            $body .= $decorate->body();
        }
        return $body;
    }

    public function getSubject()
    {
        /** @var EmailDecoratorInterface $decorate */
        foreach ($this->decoratorCollection as $decorate) {
            $decorate->subject();
        }
        return $this->subject;
    }

    /**
     * @return \Project
     */
    public function getProject()
    {
        return $this->project;
    }

    /**
     * Returns true if this message has recipients. Recipients are determined by these factors:
     *   1] Is the project setting to email broken submissions to true
     *   2] Is the build group setting for email set to true
     *   3] Does the message have content, for example, if it is an update are there errors?
     *   4] Does the user's email preferences match the content of the email
     *   5] Does this build fix something that the user is subscribed to?
     * @return boolean
     */
    public function hasRecipients()
    {
        if ($this->project->EmailBrokenSubmission == 0) {
            return false;
        }

        if ($this->buildGroup->GetSummaryEmail() === \BuildGroup::EMAIL_NONE) {
            return false;
        }

        return parent::hasRecipients();
    }

    /**
     * @return \UserProject[]
     */
    public function getSubscribers()
    {
        if (empty($this->subscribers)) {
            $this->subscribers = $this->project->GetProjectSubscribers();
        }
        return $this->subscribers;
    }

    /**
     * @param bool $includeUnregistered
     * @return \UserProject[]
     */
    public function getAuthors($includeUnregistered = false)
    {
        if (empty($this->authors)) {
            foreach ($this->buildCollection as $label => $build) {
                $this->authors = array_merge(
                    $this->authors,
                    $build->GetAuthorsAndCommitters($includeUnregistered)
                );
            }
        }
        return $this->authors;
    }

    /**
     * @return mixed
     */
    public function getMessages()
    {
        // TODO: project settings should be checked here to see if authors/committers notified
        /*
        if ($project->NotifyAuthors) {
            foreach (this->buildCollection as $label => $build) {
                foreach ($build->GetAuthorsAndCommitters() as $email => $author) {
                    $this->recipientCollection->add($author, $email);
                }
            }
        }
        */

        foreach ($this->decoratorCollection as $decorator) {
            foreach ($this->subscribers as $email => $recipient) {
                $hasMessage = isset($this->messages[$email]);
                $body = $hasMessage ? $this->messages[$email]['body'] : '';
                $subject = $hasMessage ? $this->messages[$email]['subject'] : '';

                $this->messages[$email] = [
                    'body' => $body . $decorator->body(),
                    'subject' => $subject . $decorator->subject(),
                ];
            }
        }

        return $this->messages;
    }
}

<?php
namespace CDash\archive\Messaging\Email\Decorator;

use CDash\Config;
use CDash\Messaging\Collection\BuildFailureCollection;
use CDash\Messaging\Collection\Collection;
use CDash\Messaging\Email\EmailMessage;

class BuildFailureErrorsEmailDecorator extends EmailTemplateDecorator
{
    private $bodyTemplate = "*Errors*\n%s";
    private $itemTemplate = "%s (%s/viewBuildError.php?type=0&buildid=%u)\n%s%s\n";
    private $subjectTemplate = '';

    public function getBodyTemplate()
    {
        return $this->bodyTemplate;
    }

    public function getSubjectTemplate()
    {
        return $this->subjectTemplate;
    }

    public function getTemplateTopicItems(\Build $build, $label)
    {
        return $build->GetFailures(['Type' => \Build::TYPE_ERROR]);
    }

    /**
     * @param $topic
     * @return string
     */
    public function getItemTemplate ($topic)
    {
        return $this->itemTemplate;
    }

    /**
     * @param \BuildFailure $topic
     * @return array
     * @throws \TypeError
     */
    public function getItemTemplateValues($topic)
    {
        // Config needs to be refactored out
        $baseUrl = Config::getBaseUrl();
        $srcFile = $topic->SourceFile ? $topic->SourceFile : '';
        $buildId = $topic->BuildId;
        $stdOut = $topic->StdOutput ? "{$topic->StdOutput}\n" : '';
        $stdErr = $topic->StdError  ? "{$topic->StdError}\n"  : '';
        return [$srcFile, $baseUrl, $buildId, $stdOut, $stdErr];
    }

    /**
     * Returns the topic of the email, e.g. dynamic analysis, or test failure, etc.
     * @return string
     */
    public function getTopicName()
    {
        return self::TOPIC_ERROR;
    }

    /**
     * This returns true if the $userLabels argument intersects with any topic labels
     * @return boolean
     */
    public function hasLabels(array $userLabels)
    {
        // TODO: Implement hasLabels() method.
    }
}

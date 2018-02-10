<?php
namespace CDash\archive\Messaging\Email\Decorator;

use CDash\Config\Config;

class BuildFailureWarningsEmailDecorator extends EmailTemplateDecorator
{
    private $bodyTemplate = "*Warnings*\n%s";
    private $itemTemplate = "%s (%s/viewBuildError.php?type=0&buildid=%u)\n%s%s\n";
    private $subjectTemplate = "";


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
        return $build->GetFailures(['Type' => \Build::TYPE_WARN]);
    }

    public function getItemTemplateValues($topic)
    {
        $baseUrl = Config::getBaseUrl();
        $srcFile = $topic->SourceFile ? $topic->SourceFile : '';
        $buildId = $topic->BuildId;
        $stdOut = $topic->StdOutput ? "{$topic->StdOutput}\n" : '';
        $stdErr = $topic->StdError  ? "{$topic->StdError}\n"  : '';
        return [$srcFile, $baseUrl, $buildId, $stdOut, $stdErr];
    }

    public function getItemTemplate($topic)
    {
        return $this->itemTemplate;
    }

    /**
     * Returns the topic of the email, e.g. dynamic analysis, or test failure, etc.
     * @return string
     */
    public function getTopicName()
    {
        return self::TOPIC_WARNING;
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

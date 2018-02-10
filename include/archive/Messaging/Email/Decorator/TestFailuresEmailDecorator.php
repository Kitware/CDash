<?php
namespace CDash\archive\Messaging\Email\Decorator;

class TestFailuresEmailDecorator extends EmailTemplateDecorator
{
    private $bodyTemplate = "*Tests failing*\n%s";
    private $itemTemplate = "%s | %s | (%s)\n";
    private $subjectTemplate = '';

    /**
     * This returns true if the $userLabels argument intersects with any topic labels
     * @return boolean
     */
    public function hasLabels(array $userLabels)
    {
        // TODO: Implement hasLabels() method.
    }

    /**
     * Returns the topic of the email, e.g. dynamic analysis, or test failure, etc.
     * @return string
     */
    public function getTopicName()
    {
        return self::TOPIC_TEST;
    }

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
        return $build->GetFailedTests();
    }

    public function getItemTemplateValues($topic)
    {
        return [$topic->Name, $topic->Details, $topic->Id];
    }

    public function getItemTemplate($topic)
    {
        return $this->itemTemplate;
    }
}

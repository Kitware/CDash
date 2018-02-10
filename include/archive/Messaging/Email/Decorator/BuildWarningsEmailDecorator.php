<?php
namespace CDash\archive\Messaging\Email\Decorator;

use CDash\Config\Config;
use CDash\Messaging\Collection\BuildErrorCollection;
use CDash\Messaging\Collection\Collection;
use CDash\Messaging\Email\EmailMessage;

class BuildWarningsEmailDecorator extends EmailTemplateDecorator
{
    private $bodyTemplate = "*Warnings*\n%s";
    private $subjectTemplate = '';
    private $itemTemplate = "%s line %u (%s/viewBuildError.php?type=1&buildid=%u)\n%s";
    private $itemTemplateNoSrcFile = "%s\n%s\n";

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
        return $build->GetErrors(['Type' => \Build::TYPE_WARN]);
    }

    public function getItemTemplateValues($topic)
    {
        $values = [];
        if ($topic->SourceFile) {
            $values[] = $topic->SourceFile;
            $values[] = $topic->SourceLine;
            $values[] = Config::getBaseUrl();
            $values[] = $topic->BuildId;
            $values[] = $topic->Text;
        } else {
            $values[] = $topic->Text;
            $values[] = $topic->PostContext;
        }
        return $values;
    }

    public function getItemTemplate($topic)
    {
        if ($topic->SourceFile) {
            return $this->itemTemplate;
        } else {
            return $this->itemTemplateNoSrcFile;
        }
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

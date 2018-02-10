<?php
namespace CDash\archive\Messaging\Email\Decorator;

abstract class EmailTemplateDecorator extends EmailDecorator implements DecoratorTemplateInterface
{
    public function body()
    {
        $body = '';
        $bodyTemplate = $this->getBodyTemplate();

        foreach ($this->topicCollection as $topic) {
            $itemTemplate = $this->getItemTemplate($topic);
            $values = $this->getItemTemplateValues($topic);
            $body .= vsprintf($itemTemplate, $values);
        }

        $body = preg_replace("/\n+/", "\n", $body);
        return trim(sprintf($bodyTemplate, $body));
    }

    public function hasTopic()
    {
        $builds = $this->message->getBuilds();
        $hasTopic = false;

        /** @var \Build $build */
        foreach ($builds as $label => $build) {
            $topicItems = $this->getTemplateTopicItems($build, $label);
            if (!empty($topicItems)) {
                foreach ($topicItems as $item) {
                    $this->topicCollection->add($item);
                }
                $hasTopic = true;
            }
        }
        return $hasTopic;
    }

    /**
     * @return string
     */
    public function subject()
    {
        // TODO: Implement subject() method.
    }
}

<?php
namespace CDash\Messaging\Notification\Email\Decorator;

use CDash\Collection\CollectionInterface;
use CDash\Messaging\Topic\TopicInterface;

abstract class Decorator implements DecoratorInterface
{
    /** @var  DecoratorInterface $body */
    protected $body;

    /** @var string $text */
    protected $text = '';

    /** @var  int $maxTopicItems */
    protected $maxTopicItems;

    /**
     * Decorator constructor.
     * @param DecoratorInterface|null $body
     */
    public function __construct(DecoratorInterface $body = null)
    {
        $this->body = $body;
    }

    /**
     * @param int $maxTopicItems
     * @return $this
     */
    public function setMaxTopicItems(int $maxTopicItems)
    {
        $this->maxTopicItems = $maxTopicItems;
        return $this;
    }

    /**
     * @param string $template
     * @param array $data
     * @return string
     */
    public function decorateWith(string $template, array $data)
    {
        // This prevents users from having to create multi-dimensional arrays where none
        // are needed.
        if (!isset($data[0])) {
            $data = [$data];
        }

        $rx = '/{{ (.*?) }}/';
        $body = '';

        foreach ($data as $row) {
            if (preg_match_all($rx, $template, $match)) {
                $tmpl = $template;
                foreach ($match[1] as $property_name) {
                    $property = isset($row[$property_name]) ? $row[$property_name] : '';
                    $tmpl = str_replace("{{ {$property_name} }}", $property, $tmpl);
                }
                $body .= $tmpl;
            }
        }

        return $body;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $string = $this->body ? "{$this->body}" : '';

        if (!empty($this->text)) {
            $string .= "{$this->text}\n";
        }

        return $string;
    }
}

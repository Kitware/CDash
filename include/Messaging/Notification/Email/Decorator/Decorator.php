<?php
namespace CDash\Messaging\Notification\Email\Decorator;

use CDash\Collection\CollectionInterface;
use CDash\Messaging\Topic\TopicInterface;

abstract class Decorator implements DecoratorInterface
{
    /** @var  DecoratorInterface $decorator */
    protected $decorator;

    /** @var  DecoratorInterface $body */
    protected $body = '';

    protected $description = '';

    protected $subject = '';

    protected $rows_processed = 0;

    /**
     * @return string
     */
    abstract protected function getTemplate();

    public function __construct(DecoratorInterface $body = null)
    {
        $this->body = $body;
    }

    /**
     * @param DecoratorInterface $decorator
     * @return DecoratorInterface
     */
    public function setDecorator(DecoratorInterface $decorator)
    {
        $this->decorator = $decorator;
        return $this;
    }

    /**
     * @param mixed $data
     */
    public function setTemplateData($data)
    {
        // TODO: Implement setTemplateData() method.
    }

    /**
     * @param array $topic
     * @return Decorator
     */
    public function decorateWith(array $topic)
    {
        // This prevents users from having to create multi-dimensional arrays where none
        // are needed.
        if (!isset($topic[0])) {
            $topic = [$topic];
        }

        $template = $this->getTemplate();

        $rx = '/{{ (.*?) }}/';
        $body = '';

        foreach ($topic as $row) {
            if (preg_match_all($rx, $template, $match)) {
                $tmpl = $template;
                foreach ($match[1] as $property_name) {
                    $property = isset($row[$property_name]) ? $row[$property_name] : '';
                    $tmpl = str_replace("{{ {$property_name} }}", $property, $tmpl);
                }
                $body .= $tmpl;
            }
        }

        $this->rows_processed = count($topic);
        $this->body .= $body;
        return $this;
    }

    public function addSubject($subject)
    {
        $this->subject_collection->add($subject);
    }

    /**
     * @return string
     */
    public function __toString()
    {
        $string = $this->decorator ? "{$this->decorator}" : '';

        if (!empty($this->description)) {
            $string .= "*{$this->description}*\n";
        }

        if (!empty($this->body)) {
            $string .= "{$this->body}\n";
        }

        return $string;
    }

    /**
     * @return string
     */
    public function getDescription()
    {
        return $this->description;
    }
}

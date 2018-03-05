<?php
namespace CDash\Messaging\Notification\Email\Decorator;

use CDash\Config;
use CDash\Messaging\Topic\Topic;

class FooterDecorator extends Decorator
{
    private $template = "-CDash on {{ server_name }}";

    /**
     * @param Topic $topic
     */
    public function setTopic(Topic $topic)
    {
    }

    /**
     * @param Config $config
     * @return string
     */
    public function createFooter(Config $config)
    {
        $data = ['server_name' => $config->getServer()];
        $this->text = $this->decorateWith($this->template, $data);
        return $this->text;
    }
}

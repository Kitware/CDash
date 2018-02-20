<?php
namespace CDash\Messaging\Notification\Email\Decorator;

class FooterDecorator extends Decorator
{
    private $template = "-CDash on {{ server_name }}";

    public function addSubject($subject)
    {
        $data = ['server_name' => $subject->getServer()];
        $this->text = $this->decorateWith($this->template, $data);

    }
}

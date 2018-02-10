<?php
namespace CDash\Messaging\Notification\Email\Decorator;

class FooterDecorator extends Decorator
{

    /**
     * @return string
     */
    protected function getTemplate()
    {
        return "-CDash on {{ server_name }}";
    }
}

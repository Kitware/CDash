<?php
namespace CDash\archive\Messaging\Email\Decorator;


interface DecoratorTemplateInterface
{
    public function getBodyTemplate();
    public function getSubjectTemplate();
    public function getTemplateTopicItems(\Build $build, $label);
    public function getItemTemplateValues($topic);
    public function getItemTemplate($topic);
}

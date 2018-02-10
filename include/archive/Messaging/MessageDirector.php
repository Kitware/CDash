<?php
namespace CDash\archive\Messaging;

use BuildGroup;
use Project;

class MessageDirector
{
    public function build(BuilderInterface $builder)
    {
        return $builder
            ->createMessage()
            ->addProject()
            ->addBuildGroup()
            ->addBuildCollection()
            ->addDecoratorsToCollection()
            ->getMessage();
    }
}

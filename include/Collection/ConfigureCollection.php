<?php
namespace CDash\Collection;

use CDash\Messaging\Topic\ConfigureTopic;

class ConfigureCollection extends Collection
{
    public function add(\BuildConfigure $configure)
    {
        parent::addItem($configure, 'Configure');
    }
}

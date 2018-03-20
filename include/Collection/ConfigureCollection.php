<?php
namespace CDash\Collection;

use CDash\Messaging\Topic\ConfigureTopic;

class ConfigureCollection extends Collection
{
    /**
     * @param \BuildConfigure $configure
     * @return $this
     */
    public function add(\BuildConfigure $configure)
    {
        parent::addItem($configure, 'Configure');
        return $this;
    }
}

<?php
namespace CDash\Collection;

use CDash\Messaging\Topic\ConfigureTopic;
use CDash\Model\BuildConfigure;

class ConfigureCollection extends Collection
{
    /**
     * @param BuildConfigure $configure
     * @return $this
     */
    public function add(BuildConfigure $configure)
    {
        parent::addItem($configure, 'Configure');
        return $this;
    }
}

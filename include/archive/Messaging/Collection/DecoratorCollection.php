<?php
namespace CDash\archive\Messaging\Collection;

use CDash\Messaging\DecoratorInterface;

class DecoratorCollection extends Collection
{
    /**
     * @param DecoratorInterface $decorator
     * @return $this
     */
    protected function addDecorator(DecoratorInterface $decorator)
    {
        return parent::add($decorator);
    }

    public function add($item, $name = null)
    {
        return $this->addDecorator($item);
    }
}

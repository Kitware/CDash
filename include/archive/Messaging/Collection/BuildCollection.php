<?php
namespace CDash\archive\Messaging\Collection;

class BuildCollection extends Collection
{
    /**
     * @param \Build $build
     * @return $this
     */
    protected function addBuild(\Build $build, $name)
    {
        return parent::add($build, $name);
    }

    /**
     * @param mixed $item
     * @param string|null $name
     * @return BuildCollection
     */
    public function add($item, $name = null)
    {
        return $this->addBuild($item, $name);
    }
}

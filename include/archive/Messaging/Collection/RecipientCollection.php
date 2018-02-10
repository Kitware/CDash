<?php
namespace CDash\archive\Messaging\Collection;

class RecipientCollection extends Collection
{
    private function addRecipient(\UserProject $user, $label)
    {
        parent::add($user, $label);
    }

    public function add($item, $name = null)
    {
        $this->addRecipient($item, $name);
    }
}

<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use Exception;

abstract class AbstractMutation
{
    public ?string $message = null;

    /**
     * @param array<string,mixed> $args
     */
    final public function __invoke(null $_, array $args): self
    {
        try {
            $this->mutate($args);
        } catch (Exception $e) {
            report($e);
            $this->message = $e->getMessage();
        }

        return $this;
    }

    /**
     * TODO: It would be good to strengthen the type safety of the $args array eventually.
     *
     * @param array<string,mixed> $args
     */
    abstract protected function mutate(array $args): void;
}

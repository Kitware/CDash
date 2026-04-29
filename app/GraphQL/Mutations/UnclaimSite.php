<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Exceptions\GraphQLMutationException;
use App\Models\Site;
use App\Models\User;

final class UnclaimSite extends AbstractMutation
{
    public ?Site $site = null;
    public ?User $user = null;

    /** @param array{
     *     siteId: int
     * } $args
     *
     * @throws GraphQLMutationException
     */
    public function __invoke(null $_, array $args): self
    {
        /** @var ?User $user */
        $user = auth()->user();

        if ($user === null) {
            throw new GraphQLMutationException('Authentication required to unclaim sites.');
        }

        $site = Site::find((int) $args['siteId']);

        if ($site === null) {
            throw new GraphQLMutationException('Requested site not found.');
        }

        $site->maintainers()->detach($user);

        $this->site = $site;
        $this->user = $user;

        return $this;
    }
}

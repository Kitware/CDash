<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\Site;
use App\Models\User;

final class UnclaimSite extends AbstractMutation
{
    public ?Site $site = null;
    public ?User $user = null;

    /** @param array{
     *     siteId: int
     * } $args
     */
    protected function mutate(array $args): void
    {
        /** @var ?User $user */
        $user = auth()->user();

        if ($user === null) {
            abort(401, 'Authentication required to unclaim sites.');
        }

        $site = Site::find((int) $args['siteId']);

        if ($site === null) {
            abort(404, 'Requested site not found.');
        }

        $site->maintainers()->detach($user);

        $this->site = $site;
        $this->user = $user;
    }
}

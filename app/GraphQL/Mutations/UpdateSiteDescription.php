<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\Site;
use App\Models\User;

final class UpdateSiteDescription extends AbstractMutation
{
    public ?Site $site = null;

    /** @param array{
     *     siteId: int,
     *     description: string
     * } $args
     */
    protected function mutate(array $args): void
    {
        /** @var ?User $user */
        $user = auth()->user();

        if ($user === null) {
            abort(401, 'Authentication required to edit site descriptions.');
        }

        $site = Site::find((int) $args['siteId']);

        if ($site === null) {
            abort(404, 'Requested site not found.');
        }

        $newSiteInformation = $site->mostRecentInformation?->getAttributes() ?? [];
        unset($newSiteInformation['id']);
        unset($newSiteInformation['siteid']);
        unset($newSiteInformation['timestamp']);
        $newSiteInformation['description'] = $args['description'];
        $site->information()->create($newSiteInformation);

        $this->site = $site;
    }
}

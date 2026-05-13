<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Exceptions\GraphQLMutationException;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Log;

final class UpdateSiteDescription extends AbstractMutation
{
    public ?Site $site = null;

    /** @param array{
     *     siteId: int,
     *     description: string
     * } $args
     *
     * @throws GraphQLMutationException
     */
    public function __invoke(null $_, array $args): self
    {
        /** @var ?User $user */
        $user = auth()->user();

        if ($user === null) {
            throw new GraphQLMutationException('Authentication required to edit site descriptions.');
        }

        $site = Site::find((int) $args['siteId']);

        if ($site === null) {
            throw new GraphQLMutationException('Requested site not found.');
        }

        $newSiteInformation = $site->mostRecentInformation?->getAttributes() ?? [];
        unset($newSiteInformation['id']);
        unset($newSiteInformation['siteid']);
        unset($newSiteInformation['timestamp']);
        $newSiteInformation['description'] = $args['description'];
        $site->information()->create($newSiteInformation);

        Log::info("User {$user->id} updated description for site {$site->id}.");

        $this->site = $site;

        return $this;
    }
}

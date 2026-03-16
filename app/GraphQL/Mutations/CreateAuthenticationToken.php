<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\AuthToken;
use App\Models\Project;
use App\Utils\AuthTokenUtil;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

final class CreateAuthenticationToken extends AbstractMutation
{
    public ?string $rawToken = null;
    public ?AuthToken $token = null;

    /**
     * @param array{
     *     projectId: ?int,
     *     scope: string,
     *     description: ?string,
     *     expiration: Carbon,
     * } $args
     */
    public function __invoke(null $_, array $args): self
    {
        $user = auth()->user();
        if ($user === null) {
            throw new AuthenticationException('This action is unauthorized.');
        }

        if (isset($args['projectId'])) {
            $project = Project::find((int) $args['projectId']);
            Gate::authorize('createAuthToken', $project);
        }

        [
            'token' => $this->token,
            'raw_token' => $this->rawToken,
        ] = AuthTokenUtil::generateToken(
            $user->id,
            (int) ($args['projectId'] ?? -1),
            $args['scope'],
            $args['description'] ?? '',
            $args['expiration'],
        );

        return $this;
    }
}

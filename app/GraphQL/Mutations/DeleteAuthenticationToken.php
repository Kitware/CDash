<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Models\AuthToken;
use App\Utils\AuthTokenUtil;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Log;

final class DeleteAuthenticationToken extends AbstractMutation
{
    /**
     * @param array{
     *     tokenId: int,
     * } $args
     */
    public function __invoke(null $_, array $args): self
    {
        $token = AuthToken::find((int) $args['tokenId']);
        $userid = auth()->user()->id ?? null;

        // This method performs its own authorization checks.
        // TODO: Move the logic to this method and delete the utils method.
        if ($userid === null || !AuthTokenUtil::deleteToken($token->hash ?? '', $userid)) {
            throw new AuthenticationException('This action is unauthorized.');
        }

        Log::info("User {$userid} deleted authentication token {$args['tokenId']}.");

        return $this;
    }
}

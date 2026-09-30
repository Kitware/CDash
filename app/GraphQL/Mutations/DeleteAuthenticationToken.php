<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations;

use App\Enums\AuthTokenScope;
use App\Models\AuthToken;
use App\Models\User;
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
        $user = auth()->user();
        $token = AuthToken::find((int) $args['tokenId']);

        if ($user === null || $token === null || !self::canDelete($user, $token)) {
            throw new AuthenticationException('This action is unauthorized.');
        }

        $token->delete();

        Log::info("User {$user->id} deleted authentication token {$args['tokenId']}.");

        return $this;
    }

    private static function canDelete(User $user, AuthToken $token): bool
    {
        // All tokens can be deleted by:
        // 1. The user who created them
        // 2. A system administrator
        if ($token->userid === $user->id || $user->admin) {
            return true;
        }

        // Project-scoped submit-only tokens can also be deleted by a project administrator.
        return $token->scope === AuthTokenScope::SUBMIT_ONLY
            && $token->project?->administrators()->whereKey($user->id)->exists() === true;
    }
}

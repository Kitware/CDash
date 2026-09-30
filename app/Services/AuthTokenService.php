<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AuthTokenScope;
use App\Models\AuthToken;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class AuthTokenService extends AbstractService
{
    /**
     * Contract: we assume that $user_id has already been validated and blindly create a token
     * for the user specified.  It is the responsibility of anyone who uses this function to
     * ensure that the $user_id has been properly authenticated, and that the user is authorized
     * to create a token for the specified project.
     *
     * @return array{raw_token: string, token: AuthToken}
     *
     * @throws InvalidArgumentException
     */
    public static function generate(
        int $user_id,
        int $project_id,
        AuthTokenScope $scope,
        string $description,
        ?Carbon $expiration = null,
    ): array {
        // 86 characters generates more than 512 bits of entropy (and is thus limited by the entropy of the hash)
        $token = Str::password(86, true, true, false);
        $params['hash'] = hash('sha512', $token);

        $params['userid'] = $user_id;

        $duration = Config::get('cdash.token_duration');
        $now = time();
        $params['created'] = gmdate(FMT_DATETIME, $now);

        if (!is_numeric($duration) || (int) $duration < 0) {
            Log::error("Invalid token_duration configuration {$duration}");
            throw new InvalidArgumentException('Invalid token_duration configuration');
        }

        // We trust the expiration to be pre-validated if one was provided.  If an expiration wasn't
        // provided, we set the expiration to the maximum allowed.  In the future, the expiration
        // argument should be mandatory.
        if ($expiration !== null) {
            $params['expires'] = $expiration;
        } else {
            if ((int) $duration === 0) {
                // this token "never" expires
                $params['expires'] = '9999-01-01 00:00:00';
            } else {
                $params['expires'] = gmdate(FMT_DATETIME, $now + $duration);
            }
        }

        $params['description'] = $description;

        if ($scope === AuthTokenScope::FULL_ACCESS && Config::get('cdash.allow_full_access_tokens') !== true) {
            Log::error('Full-access tokens are prohibited by config');
            throw new InvalidArgumentException('Full-access tokens are prohibited by config');
        }
        if ($scope === AuthTokenScope::SUBMIT_ONLY && $project_id < 0
                && Config::get('cdash.allow_submit_only_tokens') !== true) {
            Log::error('Only project-specific submit-only tokens allowed by config');
            throw new InvalidArgumentException('Only project-specific submit-only tokens allowed by config');
        }
        $params['scope'] = $scope;

        $params['projectid'] = $scope === AuthTokenScope::SUBMIT_ONLY && $project_id > -1 ? $project_id : null;
        if ($params['projectid'] !== null && !Project::whereKey($params['projectid'])->exists()) {
            Log::error('Invalid project');
            throw new InvalidArgumentException('Invalid project');
        }

        $auth_token = AuthToken::create($params);
        return [
            'raw_token' => $token,
            'token' => $auth_token,
        ];
    }

    /**
     * Accepts a hashed token and a project, and decides whether the token is valid for the
     * specified project and associated user.
     */
    public static function check(string $token_hash, int $project_id): bool
    {
        $auth_token = AuthToken::firstWhere('hash', $token_hash);
        if ($auth_token === null) {
            Log::error('Invalid Token');
            return false;
        }

        $user = User::find($auth_token['userid']);
        if ($user === null) {
            Log::error('Invalid UserId');
            return false;
        }

        // Expired tokens are deleted by the PruneAuthTokens job, not here.
        if ($auth_token->expires->isPast()) {
            Log::error('Invalid Token');
            return false;
        }

        $project = Project::find($project_id);
        if ($project === null || Gate::forUser($user)->denies('view', $project)) {
            Log::error('Invalid Project');
            return false;
        }

        switch ($auth_token->scope) {
            case AuthTokenScope::SUBMIT_ONLY:
                // If a token is submit-only and is project-specific, make sure it matches the right project
                if ($auth_token['projectid'] !== null && $project_id !== $auth_token['projectid']) {
                    Log::error('Invalid Project');
                    return false;
                }
                if (($auth_token['projectid'] === null || $project_id !== $auth_token['projectid'])
                        && Config::get('cdash.allow_submit_only_tokens') !== true) {
                    Log::error('Submit-only token used when disallowed by config');
                    return false;
                }
                break;
            case AuthTokenScope::FULL_ACCESS:
                if (Config::get('cdash.allow_full_access_tokens') !== true) {
                    Log::error('Full-access token used when disallowed by config');
                    return false;
                }
                break;
        }

        return true;
    }

    public static function hash(?string $unhashed_token): string
    {
        if ($unhashed_token === null || $unhashed_token === '') {
            return '';
        }

        return hash('sha512', $unhashed_token);
    }
}

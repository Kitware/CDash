<?php

namespace App\GraphQL\Validators;

use App\Enums\AuthTokenScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Validation\Rule;
use Nuwave\Lighthouse\Validation\Validator;

final class CreateAuthenticationTokenInputValidator extends Validator
{
    public function rules(): array
    {
        $allowFullAccessTokens = Config::get('cdash.allow_full_access_tokens') === true;
        $allowSubmitOnlyTokens = Config::get('cdash.allow_submit_only_tokens') === true;

        $validScopes = [AuthTokenScope::SUBMIT_ONLY];
        if ($allowFullAccessTokens) {
            $validScopes[] = AuthTokenScope::FULL_ACCESS;
        }

        $isSubmitOnly = $this->arg('scope') === AuthTokenScope::SUBMIT_ONLY;

        $durationConfig = (int) Config::get('cdash.token_duration');
        $maximumExpiration = $durationConfig === 0 ? Carbon::now()->endOfMillennium() : Carbon::now()->addSeconds($durationConfig);

        return [
            'scope' => [
                'required',
                Rule::enum(AuthTokenScope::class)->only($validScopes),
            ],
            'projectId' => [
                Rule::prohibitedIf(!$isSubmitOnly),
                Rule::requiredIf(!$allowFullAccessTokens && !$allowSubmitOnlyTokens),
                Rule::requiredIf(!$allowSubmitOnlyTokens && $isSubmitOnly),
            ],
            'expiration' => [
                'required',
                Rule::date()->future(),
                Rule::date()->beforeOrEqual($maximumExpiration),
            ],
        ];
    }
}

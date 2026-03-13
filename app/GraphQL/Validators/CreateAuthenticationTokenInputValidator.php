<?php

namespace App\GraphQL\Validators;

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

        $validScopes = ['submit_only'];
        if ($allowFullAccessTokens) {
            $validScopes[] = 'full_access';
        }

        $durationConfig = (int) Config::get('cdash.token_duration');
        $maximumExpiration = $durationConfig === 0 ? Carbon::now()->endOfMillennium() : Carbon::now()->addSeconds($durationConfig);

        return [
            'scope' => [
                'required',
                Rule::in($validScopes),
            ],
            'projectId' => [
                'prohibited_unless:scope,submit_only',
                Rule::requiredIf(!$allowFullAccessTokens && !$allowSubmitOnlyTokens),
                Rule::requiredIf(!$allowSubmitOnlyTokens && $this->arg('scope') === 'submit_only'),
            ],
            'expiration' => [
                'required',
                Rule::date()->future(),
                Rule::date()->beforeOrEqual($maximumExpiration),
            ],
        ];
    }
}

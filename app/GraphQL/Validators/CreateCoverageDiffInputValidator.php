<?php

declare(strict_types=1);

namespace App\GraphQL\Validators;

use Nuwave\Lighthouse\Validation\Validator;

final class CreateCoverageDiffInputValidator extends Validator
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'baseBuildId' => [
                'required',
            ],
            'compareBuildId' => [
                'required',
                'different:baseBuildId',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'compareBuildId.different' => 'The base and compare builds must be different.',
        ];
    }
}
